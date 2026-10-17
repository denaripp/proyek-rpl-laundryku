<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InvalidOrderTransitionException;
use App\Http\Controllers\Controller;
use App\Models\CourierProfile;
use App\Models\CourierStoreAssignment;
use App\Models\Order;
use App\Models\OrderAssignment;
use App\Models\Store;
use App\Services\OrderStateMachine;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CourierTaskController extends Controller
{
    use ApiResponse;

    protected OrderStateMachine $stateMachine;

    public function __construct(OrderStateMachine $stateMachine)
    {
        $this->stateMachine = $stateMachine;
    }

    /**
     * Resolve courier's active store assignment.
     */
    protected function getActiveCourierAssignment(Request $request): ?CourierStoreAssignment
    {
        $user = $request->user();

        return CourierStoreAssignment::whereHas('courier', fn ($q) => $q->where('user_id', $user->id))
            ->where('status', 'active')
            ->with(['store', 'courier'])
            ->first();
    }

    /**
     * Format an order into a clean courier task resource.
     */
    protected function formatCourierTask(Order $order, string $currentUserId): array
    {
        $taskType = in_array($order->status, ['PENDING_PICKUP', 'PICKUP_ASSIGNED', 'PICKED_UP']) ? 'pickup' : 'delivery';
        $isAssignedToMe = (string) $order->courier_id === (string) $currentUserId;

        return [
            'id' => $order->id,
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'task_type' => $taskType,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'is_assigned_to_me' => $isAssignedToMe,
            'courier_id' => $order->courier_id,
            'courier_name' => $order->courier?->name,
            'customer_name' => $order->customer?->name,
            'customer_phone' => $order->customer?->phone,
            'store_id' => $order->store_id,
            'store_name' => $order->store?->name,
            'store_address' => $order->store?->address_text,
            'pickup_address' => $order->pickup_address_snapshot,
            'delivery_address' => $order->delivery_address_snapshot,
            'estimated_amount' => $order->estimated_amount,
            'final_amount' => $order->final_amount,
            'estimated_weight_kg' => $order->estimated_weight_kg ? (float) $order->estimated_weight_kg : null,
            'actual_weight_kg' => $order->actual_weight_kg ? (float) $order->actual_weight_kg : null,
            'customer_notes' => $order->customer_notes,
            'items_count' => $order->items?->count() ?? 0,
            'items' => $order->items?->map(fn ($item) => [
                'id' => $item->id,
                'service_name' => $item->service_name_snapshot,
                'pricing_unit' => $item->pricing_unit_snapshot,
                'quantity_estimated' => (float) $item->quantity_estimated,
                'quantity_actual' => $item->quantity_actual ? (float) $item->quantity_actual : null,
            ]),
            'created_at' => $order->created_at?->toIso8601String(),
            'updated_at' => $order->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Get Courier Profile & Dashboard Summary (US-COU-02, US-COU-06).
     */
    public function profile(Request $request): JsonResponse
    {
        $assignment = $this->getActiveCourierAssignment($request);

        if (!$assignment) {
            return $this->errorResponse(
                'NO_ACTIVE_STORE',
                'Anda belum terikat secara aktif pada toko laundry manapun. Hubungi admin toko untuk proses rekrutmen.',
                [
                    'is_assigned' => false,
                ],
                403
            );
        }

        $userId = $request->user()->id;
        $storeId = $assignment->store_id;

        $availableCount = Order::where('store_id', $storeId)
            ->whereNull('courier_id')
            ->whereIn('status', ['PENDING_PICKUP', 'READY_FOR_DELIVERY'])
            ->count();

        $activeCount = Order::where('store_id', $storeId)
            ->where('courier_id', $userId)
            ->whereIn('status', ['PICKUP_ASSIGNED', 'PICKED_UP', 'DELIVERY_ASSIGNED', 'OUT_FOR_DELIVERY'])
            ->count();

        $completedCount = Order::where('store_id', $storeId)
            ->where('courier_id', $userId)
            ->whereIn('status', ['AT_STORE', 'WEIGHED', 'AWAITING_PRICE_APPROVAL', 'PRICE_APPROVED', 'AWAITING_PAYMENT', 'PAID', 'PROCESSING', 'COMPLETED'])
            ->count();

        return $this->successResponse([
            'is_assigned' => true,
            'courier_profile_id' => $assignment->courier_id,
            'vehicle_type' => $assignment->courier?->vehicle_type,
            'verification_status' => $assignment->courier?->verification_status,
            'assignment_status' => $assignment->status,
            'started_at' => $assignment->started_at?->toIso8601String(),
            'store' => [
                'id' => $assignment->store?->id,
                'name' => $assignment->store?->name,
                'address_text' => $assignment->store?->address_text,
                'service_radius_km' => $assignment->store?->service_radius_km,
            ],
            'stats' => [
                'available_tasks' => $availableCount,
                'active_tasks' => $activeCount,
                'completed_tasks' => $completedCount,
            ],
        ]);
    }

    /**
     * List tasks for courier strictly scoped to active store (FR-COURIER-03, US-COU-03).
     */
    public function tasks(Request $request): JsonResponse
    {
        $assignment = $this->getActiveCourierAssignment($request);

        if (!$assignment) {
            return $this->errorResponse(
                'NO_ACTIVE_STORE',
                'Anda belum terikat secara aktif pada toko laundry manapun.',
                null,
                403
            );
        }

        $userId = $request->user()->id;
        $storeId = $assignment->store_id;
        $tab = $request->query('tab', 'all');

        $query = Order::where('store_id', $storeId)
            ->with(['customer', 'store', 'courier', 'items']);

        switch ($tab) {
            case 'available':
                // Tasks open in store that haven't been claimed by any courier
                $query->whereNull('courier_id')
                    ->whereIn('status', ['PENDING_PICKUP', 'READY_FOR_DELIVERY']);
                break;

            case 'active':
                // Tasks assigned to this courier that are currently in progress
                $query->where('courier_id', $userId)
                    ->whereIn('status', ['PICKUP_ASSIGNED', 'PICKED_UP', 'DELIVERY_ASSIGNED', 'OUT_FOR_DELIVERY']);
                break;

            case 'completed':
                // Tasks assigned to this courier that have finished pickup or delivery
                $query->where('courier_id', $userId)
                    ->whereIn('status', ['AT_STORE', 'WEIGHED', 'AWAITING_PRICE_APPROVAL', 'PRICE_APPROVED', 'AWAITING_PAYMENT', 'PAID', 'PROCESSING', 'COMPLETED']);
                break;

            case 'all':
            default:
                // All tasks relevant: open tasks OR tasks assigned to this courier
                $query->where(function ($q) use ($userId) {
                    $q->where(function ($sub) {
                        $sub->whereNull('courier_id')
                            ->whereIn('status', ['PENDING_PICKUP', 'READY_FOR_DELIVERY']);
                    })->orWhere('courier_id', $userId);
                });
                break;
        }

        $orders = $query->orderBy('created_at', 'desc')->get();

        return $this->successResponse(
            $orders->map(fn ($order) => $this->formatCourierTask($order, $userId))
        );
    }

    /**
     * Show single task detail (US-COU-03).
     */
    public function show(Request $request, string $orderId): JsonResponse
    {
        $assignment = $this->getActiveCourierAssignment($request);
        if (!$assignment) {
            return $this->errorResponse('NO_ACTIVE_STORE', 'Anda belum terikat pada toko laundry.', null, 403);
        }

        $order = Order::where('id', $orderId)
            ->where('store_id', $assignment->store_id)
            ->with(['customer', 'store', 'courier', 'items', 'statusHistories'])
            ->first();

        if (!$order) {
            return $this->errorResponse('TASK_NOT_FOUND', 'Tugas pesanan tidak ditemukan pada toko Anda.', null, 404);
        }

        return $this->successResponse(
            $this->formatCourierTask($order, $request->user()->id)
        );
    }

    /**
     * Claim / Take available pickup or delivery task ATOMICALLY (FR-COURIER-04, FR-COURIER-05, US-COU-03).
     * Uses pessimistic row locking (lockForUpdate) to prevent race conditions when two couriers try to take the same task.
     */
    public function claim(Request $request, string $orderId): JsonResponse
    {
        $assignment = $this->getActiveCourierAssignment($request);
        if (!$assignment) {
            return $this->errorResponse('NO_ACTIVE_STORE', 'Anda belum terikat pada toko laundry aktif.', null, 403);
        }

        $user = $request->user();

        return DB::transaction(function () use ($orderId, $assignment, $user) {
            // Pessimistic lock on the order row
            $order = Order::where('id', $orderId)->lockForUpdate()->first();

            if (!$order) {
                return $this->errorResponse('TASK_NOT_FOUND', 'Pesanan tidak ditemukan.', null, 404);
            }

            // Must belong to the courier's active store
            if ($order->store_id !== $assignment->store_id) {
                return $this->errorResponse(
                    'FORBIDDEN_STORE',
                    'Anda hanya dapat mengambil tugas dari toko tempat Anda terikat secara aktif.',
                    null,
                    403
                );
            }

            // ATOMIC RACE CONDITION CHECK:
            // 1. Pickup Claim Flow
            if ($order->status === 'PENDING_PICKUP') {
                if ($order->courier_id && $order->courier_id !== $user->id) {
                    return $this->errorResponse(
                        'TASK_ALREADY_CLAIMED',
                        'Tugas penjemputan ini sudah diambil oleh kurir lain.',
                        null,
                        409
                    );
                }

                $updated = $this->stateMachine->transition(
                    $order,
                    'PICKUP_ASSIGNED',
                    $user,
                    ['courier_id' => $user->id],
                    "Kurir {$user->name} mengambil tugas penjemputan cucian."
                );

                return $this->successResponse(
                    $this->formatCourierTask($updated, $user->id),
                    ['message' => 'Tugas penjemputan berhasil diambil. Silakan menuju alamat pelanggan.']
                );
            }

            // 2. Delivery Claim Flow
            if ($order->status === 'READY_FOR_DELIVERY') {
                if ($order->courier_id && $order->courier_id !== $user->id) {
                    return $this->errorResponse(
                        'TASK_ALREADY_CLAIMED',
                        'Tugas pengantaran ini sudah diambil oleh kurir lain.',
                        null,
                        409
                    );
                }

                $updated = $this->stateMachine->transition(
                    $order,
                    'DELIVERY_ASSIGNED',
                    $user,
                    ['courier_id' => $user->id],
                    "Kurir {$user->name} mengambil tugas pengantaran pakaian bersih."
                );

                return $this->successResponse(
                    $this->formatCourierTask($updated, $user->id),
                    ['message' => 'Tugas pengantaran berhasil diambil. Silakan antar cucian ke alamat pelanggan.']
                );
            }

            // If already assigned or at other statuses
            return $this->errorResponse(
                'TASK_NOT_AVAILABLE',
                "Status pesanan saat ini ({$order->status}) tidak dapat diambil sebagai tugas baru.",
                null,
                409
            );
        });
    }

    /**
     * Courier updates status for their assigned task (FR-COURIER-06, US-COU-04, US-COU-05).
     * Valid transitions for courier:
     * - PICKUP_ASSIGNED -> PICKED_UP
     * - PICKED_UP -> AT_STORE
     * - DELIVERY_ASSIGNED -> OUT_FOR_DELIVERY
     * - OUT_FOR_DELIVERY -> COMPLETED
     */
    public function transition(Request $request, string $orderId): JsonResponse
    {
        $assignment = $this->getActiveCourierAssignment($request);
        if (!$assignment) {
            return $this->errorResponse('NO_ACTIVE_STORE', 'Anda belum terikat pada toko laundry.', null, 403);
        }

        $validated = $request->validate([
            'to_status' => ['required', 'string'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();
        $toStatus = strtoupper(trim($validated['to_status']));
        $note = $validated['note'] ?? null;

        $order = Order::where('id', $orderId)->first();
        if (!$order) {
            return $this->errorResponse('TASK_NOT_FOUND', 'Pesanan tidak ditemukan.', null, 404);
        }

        // Verify courier assignment
        if ((string) $order->courier_id !== (string) $user->id) {
            return $this->errorResponse(
                'UNAUTHORIZED_COURIER',
                'Anda bukan kurir yang ditugaskan untuk pesanan ini.',
                null,
                403
            );
        }

        // Verify store boundary
        if ($order->store_id !== $assignment->store_id) {
            return $this->errorResponse(
                'FORBIDDEN_STORE',
                'Pesanan ini bukan milik toko tempat Anda terikat.',
                null,
                403
            );
        }

        try {
            $updated = $this->stateMachine->transition(
                $order,
                $toStatus,
                $user,
                ['courier_id' => $user->id],
                $note
            );

            return $this->successResponse(
                $this->formatCourierTask($updated, $user->id),
                ['message' => "Status tugas berhasil diperbarui ke '{$toStatus}'."]
            );
        } catch (InvalidOrderTransitionException $e) {
            return $this->errorResponse(
                $e->getErrorCode(),
                $e->getMessage(),
                [
                    'from_status' => $e->fromStatus,
                    'to_status' => $e->toStatus,
                ],
                $e->getHttpStatusCode()
            );
        }
    }

    /**
     * Courier confirms receiving cash payment for COD order (FR-PAY-07, US-COU-05).
     */
    public function confirmCodPayment(Request $request, string $orderId): JsonResponse
    {
        $assignment = $this->getActiveCourierAssignment($request);
        if (!$assignment) {
            return $this->errorResponse('NO_ACTIVE_STORE', 'Anda belum terikat pada toko laundry.', null, 403);
        }

        $user = $request->user();
        $order = Order::where('id', $orderId)->first();
        if (!$order) {
            return $this->errorResponse('TASK_NOT_FOUND', 'Pesanan tidak ditemukan.', null, 404);
        }

        if ((string) $order->courier_id !== (string) $user->id && !$user->hasRole('super_admin')) {
            return $this->errorResponse('UNAUTHORIZED_COURIER', 'Anda bukan kurir yang ditugaskan untuk pesanan ini.', null, 403);
        }

        $amount = (int) ($order->final_amount ?: $order->estimated_amount);

        // Update or create payment
        $payment = \App\Models\Payment::updateOrCreate(
            [
                'order_id' => $order->id,
                'method' => 'cod',
            ],
            [
                'provider' => 'cod',
                'amount' => $amount,
                'status' => 'paid',
                'idempotency_key' => 'COD-CONFIRM-' . $order->order_number . '-' . time(),
                'paid_at' => now(),
                'payment_details' => [
                    'collected_by_courier_id' => $user->id,
                    'collected_by_courier_name' => $user->name,
                    'collected_at' => now()->toIso8601String(),
                    'amount' => $amount,
                ],
            ]
        );

        $order->payment_status = 'PAID';
        $order->save();

        return $this->successResponse(
            data: [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'payment_status' => $order->payment_status,
                'collected_amount' => $amount,
                'collected_by' => $user->name,
                'payment' => $payment,
            ],
            meta: ['message' => 'Penerimaan pembayaran COD berhasil dikonfirmasi dan dicatat ke sistem.']
        );
    }
}
