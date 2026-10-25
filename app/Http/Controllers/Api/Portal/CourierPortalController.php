<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Courier;
use App\Models\CourierTask;
use App\Models\Order;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CourierPortalController extends Controller
{
    /**
     * Resolve courier record for authenticated user.
     */
    protected function resolveCourier(Request $request): ?Courier
    {
        return Courier::where('user_id', $request->user()->id)->first();
    }

    /**
     * Format timestamp with Asia/Jakarta timezone.
     */
    protected function formatTimestamp(?string $raw, $fallback = null): ?string
    {
        if ($raw) {
            return Carbon::parse($raw, 'Asia/Jakarta')->toIso8601String();
        }
        return $fallback ? Carbon::parse($fallback)->setTimezone('Asia/Jakarta')->toIso8601String() : null;
    }

    /**
     * Format an order/task as canonical CourierTaskItem.
     */
    protected function formatCourierTask(Order $order, ?CourierTask $task, Courier $courier): array
    {
        $isPickup = in_array(strtolower($order->status), [
            'created', 'pending_pickup', 'pickup_assigned', 'picked_up',
        ], true);
        $taskType = $task?->task_type ?? ($isPickup ? 'pickup' : 'delivery');
        $isAssignedToMe = ((int) $order->courier_id === (int) $courier->user_id) || ($task && (int) $task->courier_id === (int) $courier->id);

        $pickupAddress = is_array($order->pickup_address) ? $order->pickup_address : [
            'label' => 'Alamat Jemput',
            'address_text' => is_string($order->pickup_address) ? $order->pickup_address : '',
            'latitude' => (float) ($order->pickup_latitude ?? 0),
            'longitude' => (float) ($order->pickup_longitude ?? 0),
        ];

        $deliveryAddress = is_array($order->delivery_address) ? $order->delivery_address : [
            'label' => 'Alamat Antar',
            'address_text' => is_string($order->delivery_address) ? $order->delivery_address : '',
            'latitude' => (float) ($order->delivery_latitude ?? 0),
            'longitude' => (float) ($order->delivery_longitude ?? 0),
        ];

        $rawCreated = $order->getRawOriginal('created_at');
        $rawUpdated = $order->getRawOriginal('updated_at');

        $statusMap = [
            'created' => 'PENDING_PICKUP',
            'pending_pickup' => 'PENDING_PICKUP',
            'pickup_assigned' => 'PICKUP_ASSIGNED',
            'picked_up' => 'PICKED_UP',
            'at_store' => 'AT_STORE',
            'received' => 'AT_STORE',
            'weighed' => 'WEIGHED',
            'awaiting_price_approval' => 'AWAITING_PRICE_APPROVAL',
            'price_approved' => 'PRICE_APPROVED',
            'awaiting_payment' => 'AWAITING_PAYMENT',
            'paid' => 'PAID',
            'processing' => 'PROCESSING',
            'ready_for_delivery' => 'READY_FOR_DELIVERY',
            'delivery_assigned' => 'DELIVERY_ASSIGNED',
            'out_for_delivery' => 'OUT_FOR_DELIVERY',
            'completed' => 'COMPLETED',
            'cancelled' => 'CANCELLED',
        ];

        $paymentStatusMap = [
            'unpaid' => 'UNPAID',
            'pending' => 'UNPAID',
            'paid' => 'PAID',
            'failed' => 'PAYMENT_FAILED',
            'expired' => 'PAYMENT_EXPIRED',
        ];

        return [
            'id' => (string) ($task?->id ?? $order->id),
            'order_id' => (string) $order->id,
            'order_number' => $order->order_number,
            'task_type' => $taskType,
            'status' => $statusMap[strtolower($order->status)] ?? strtoupper($order->status),
            'payment_status' => $paymentStatusMap[strtolower($order->payment_status ?? 'unpaid')] ?? strtoupper($order->payment_status ?? 'UNPAID'),
            'is_assigned_to_me' => $isAssignedToMe,
            'courier_id' => $order->courier_id ? (string) $order->courier_id : null,
            'courier_name' => $order->courier?->name,
            'customer_name' => $order->customer?->name ?? 'Pelanggan',
            'customer_phone' => $order->customer?->phone,
            'store_id' => (string) $order->store_id,
            'store_name' => $order->store?->name,
            'store_address' => $order->store?->address,
            'pickup_address' => $pickupAddress,
            'delivery_address' => $deliveryAddress,
            'estimated_amount' => (float) ($order->estimated_price ?? $order->total_amount ?? 0),
            'final_amount' => $order->final_price !== null ? (float) $order->final_price : null,
            'estimated_weight_kg' => $order->estimated_weight !== null ? (float) $order->estimated_weight : null,
            'actual_weight_kg' => $order->actual_weight !== null ? (float) $order->actual_weight : null,
            'customer_notes' => $order->customer_notes,
            'items_count' => $order->items ? $order->items->count() : 0,
            'items' => $order->items ? $order->items->map(function ($it) {
                return [
                    'id' => (string) $it->id,
                    'service_name' => $it->service_name ?? ($it->service?->name ?? 'Layanan'),
                    'pricing_unit' => $it->pricing_unit ?? 'kg',
                    'quantity_estimated' => (float) ($it->quantity_estimated ?? $it->quantity ?? 0),
                    'quantity_actual' => $it->quantity_actual !== null ? (float) $it->quantity_actual : null,
                ];
            })->values() : [],
            'created_at' => $this->formatTimestamp($rawCreated, $order->created_at),
            'updated_at' => $this->formatTimestamp($rawUpdated, $order->updated_at),
        ];
    }

    /**
     * GET /api/v1/kurir/tasks (legacy backward compatibility)
     */
    public function tasks(Request $request): JsonResponse
    {
        $courier = $this->resolveCourier($request);

        if (! $courier) {
            return response()->json([
                'success' => false,
                'message' => 'Profil kurir tidak ditemukan.',
            ], 404);
        }

        $tasks = CourierTask::where('courier_id', $courier->id)
            ->with(['order.store:id,name,phone,address', 'order.customer:id,name,phone'])
            ->latest()
            ->paginate(15);

        return response()->json([
            'courier' => [
                'id' => $courier->id,
                'is_available' => $courier->is_available,
            ],
            'tasks' => $tasks,
        ]);
    }

    /**
     * GET /api/v1/courier/profile
     */
    public function profile(Request $request): JsonResponse
    {
        $courier = $this->resolveCourier($request);

        if (! $courier) {
            return response()->json([
                'success' => false,
                'message' => 'Profil kurir tidak ditemukan.',
            ], 404);
        }

        $store = $courier->store_id ? Store::find($courier->store_id) : null;
        $rawStarted = $courier->getRawOriginal('created_at');

        $availableTasks = $store
            ? Order::where('store_id', $store->id)
                ->where(function ($q) {
                    $q->where(function ($sub) {
                        $sub->whereIn('status', ['created', 'pending_pickup'])->whereNull('courier_id');
                    })->orWhere(function ($sub) {
                        $sub->where('status', 'ready_for_delivery')->whereNull('courier_id');
                    });
                })->count()
            : 0;

        $activeTasks = CourierTask::where('courier_id', $courier->id)
            ->whereIn('status', ['assigned', 'accepted', 'arrived', 'picked_up'])
            ->count();

        $completedTasks = CourierTask::where('courier_id', $courier->id)
            ->where('status', 'delivered')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'is_assigned' => (bool) $courier->store_id,
                'courier_profile_id' => (string) $courier->id,
                'vehicle_type' => $courier->vehicle_type ?? 'Motor',
                'verification_status' => $courier->is_verified ? 'verified' : 'unverified',
                'assignment_status' => $courier->store_id ? 'active' : 'unassigned',
                'started_at' => $this->formatTimestamp($rawStarted, $courier->created_at),
                'store' => $store ? [
                    'id' => (string) $store->id,
                    'name' => $store->name,
                    'address_text' => $store->address,
                    'service_radius_km' => (float) ($store->service_radius_km ?? 10),
                ] : null,
                'stats' => [
                    'available_tasks' => $availableTasks,
                    'active_tasks' => $activeTasks,
                    'completed_tasks' => $completedTasks,
                ],
            ],
        ]);
    }

    /**
     * GET /api/v1/courier/tasks?tab=available|active|completed|all
     */
    public function taskList(Request $request): JsonResponse
    {
        $courier = $this->resolveCourier($request);

        if (! $courier) {
            return response()->json([
                'success' => false,
                'message' => 'Profil kurir tidak ditemukan.',
            ], 404);
        }

        $tab = $request->query('tab', 'all');

        $query = Order::with(['store:id,name,address', 'customer:id,name,phone', 'items', 'courierTasks']);

        if ($tab === 'available') {
            if (! $courier->store_id) {
                return response()->json(['success' => true, 'data' => []]);
            }
            $query->where('store_id', $courier->store_id)
                ->whereNull('courier_id')
                ->whereIn('status', ['created', 'pending_pickup', 'ready_for_delivery']);
        } elseif ($tab === 'active') {
            $query->where('courier_id', $courier->user_id)
                ->whereIn('status', ['pickup_assigned', 'picked_up', 'delivery_assigned', 'out_for_delivery']);
        } elseif ($tab === 'completed') {
            $query->where('courier_id', $courier->user_id)
                ->where('status', 'completed');
        } else {
            // 'all': active + completed for this courier, plus available tasks for courier's store
            $query->where(function ($q) use ($courier) {
                $q->where('courier_id', $courier->user_id);
                if ($courier->store_id) {
                    $q->orWhere(function ($sub) use ($courier) {
                        $sub->where('store_id', $courier->store_id)
                            ->whereNull('courier_id')
                            ->whereIn('status', ['created', 'pending_pickup', 'ready_for_delivery']);
                    });
                }
            });
        }

        $orders = $query->latest('created_at')->limit(50)->get();

        $items = $orders->map(function ($order) use ($courier) {
            $task = $order->courierTasks->firstWhere('courier_id', $courier->id) ?? $order->courierTasks->first();
            return $this->formatCourierTask($order, $task, $courier);
        })->values();

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    /**
     * GET /api/v1/courier/tasks/{orderId}
     */
    public function taskDetail(Request $request, $orderId): JsonResponse
    {
        $courier = $this->resolveCourier($request);

        if (! $courier) {
            return response()->json([
                'success' => false,
                'message' => 'Profil kurir tidak ditemukan.',
            ], 404);
        }

        $order = Order::with(['store:id,name,address', 'customer:id,name,phone', 'items', 'courierTasks'])
            ->where('id', $orderId)
            ->where(function ($q) use ($courier) {
                $q->where('courier_id', $courier->user_id);
                if ($courier->store_id) {
                    $q->orWhere('store_id', $courier->store_id);
                }
            })
            ->firstOrFail();

        $task = $order->courierTasks->firstWhere('courier_id', $courier->id) ?? $order->courierTasks->first();

        return response()->json([
            'success' => true,
            'data' => $this->formatCourierTask($order, $task, $courier),
        ]);
    }

    /**
     * POST /api/v1/courier/tasks/{orderId}/claim
     */
    public function claimTask(Request $request, $orderId): JsonResponse
    {
        $user = $request->user();
        $courier = $this->resolveCourier($request);

        if (! $courier) {
            return response()->json([
                'success' => false,
                'message' => 'Profil kurir tidak ditemukan.',
            ], 404);
        }

        if (! $courier->store_id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda belum terdaftar dan aktif di toko mana pun.',
            ], 403);
        }

        return DB::transaction(function () use ($courier, $orderId, $user, $request) {
            $order = Order::where('id', $orderId)
                ->where('store_id', $courier->store_id)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pesanan tidak ditemukan.',
                ], 404);
            }

            if ($order->courier_id && (int) $order->courier_id !== (int) $courier->user_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tugas ini sudah diambil oleh kurir lain.',
                ], 409);
            }

            $taskType = in_array(strtolower($order->status), ['created', 'pending_pickup'], true) ? 'pickup' : 'delivery';
            $order->courier_id = $courier->user_id;
            $order->status = ($taskType === 'pickup') ? 'pickup_assigned' : 'delivery_assigned';
            $order->save();

            $task = CourierTask::updateOrCreate(
                ['order_id' => $order->id, 'task_type' => $taskType],
                [
                    'courier_id' => $courier->id,
                    'status' => 'accepted',
                    'assigned_at' => now(),
                    'accepted_at' => now(),
                ]
            );

            AuditLog::create([
                'actor_id' => $user->id,
                'actor_role' => $user->role,
                'action' => 'COURIER_TASK_CLAIM',
                'resource_type' => 'order',
                'resource_id' => $order->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'after_value' => json_encode(['task_type' => $taskType, 'status' => $order->status]),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tugas pengantaran berhasil diambil.',
                'data' => $this->formatCourierTask($order->fresh(['items', 'store', 'customer']), $task, $courier),
            ]);
        });
    }

    /**
     * POST /api/v1/courier/tasks/{orderId}/transition
     */
    public function transitionTask(Request $request, $orderId): JsonResponse
    {
        $user = $request->user();
        $courier = $this->resolveCourier($request);

        if (! $courier) {
            return response()->json([
                'success' => false,
                'message' => 'Profil kurir tidak ditemukan.',
            ], 404);
        }

        $order = Order::find($orderId);
        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan tidak ditemukan.',
            ], 404);
        }

        if ((int) $order->courier_id !== (int) $courier->user_id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki hak akses untuk mengubah tugas pesanan ini.',
            ], 403);
        }

        $toStatus = strtolower($request->input('to_status', ''));

        // Strict allowed transitions for courier:
        // pickup flow: pickup_assigned -> picked_up -> at_store
        // delivery flow: delivery_assigned -> out_for_delivery -> delivered / completed
        $validTransitions = [
            'pickup_assigned' => ['picked_up'],
            'picked_up' => ['at_store'],
            'delivery_assigned' => ['out_for_delivery'],
            'out_for_delivery' => ['completed', 'delivered'],
        ];

        $currentStatus = strtolower($order->status);
        $allowedNextStatuses = $validTransitions[$currentStatus] ?? [];

        if (! in_array($toStatus, $allowedNextStatuses, true)) {
            return response()->json([
                'success' => false,
                'message' => "Lompatan status tidak valid dari '{$currentStatus}' ke '{$toStatus}'.",
            ], 422);
        }

        $task = CourierTask::where('order_id', $order->id)->where('courier_id', $courier->id)->first();

        $effectiveOrderStatus = ($toStatus === 'delivered') ? 'completed' : $toStatus;
        $order->status = $effectiveOrderStatus;
        if ($effectiveOrderStatus === 'completed') {
            $order->completed_at = now();
        }
        $order->save();

        if ($task) {
            $taskStatusMap = [
                'picked_up' => 'picked_up',
                'at_store' => 'picked_up',
                'out_for_delivery' => 'accepted',
                'completed' => 'delivered',
                'delivered' => 'delivered',
            ];
            $task->status = $taskStatusMap[$toStatus] ?? $task->status;
            if (in_array($toStatus, ['completed', 'delivered'], true)) {
                $task->completed_at = now();
            }
            $task->save();
        }

        AuditLog::create([
            'actor_id' => $user->id,
            'actor_role' => $user->role,
            'action' => 'COURIER_TASK_TRANSITION',
            'resource_type' => 'order',
            'resource_id' => $order->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'after_value' => json_encode(['to_status' => $toStatus, 'note' => $request->input('note')]),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status tugas berhasil diperbarui.',
            'data' => $this->formatCourierTask($order->fresh(['items', 'store', 'customer']), $task, $courier),
        ]);
    }

    /**
     * POST /api/v1/courier/tasks/{orderId}/confirm-cod
     */
    public function confirmCod(Request $request, $orderId): JsonResponse
    {
        $user = $request->user();
        $courier = $this->resolveCourier($request);

        if (! $courier) {
            return response()->json([
                'success' => false,
                'message' => 'Profil kurir tidak ditemukan.',
            ], 404);
        }

        $order = Order::where('id', $orderId)
            ->where('courier_id', $courier->user_id)
            ->firstOrFail();

        $order->payment_status = 'paid';
        $order->save();

        AuditLog::create([
            'actor_id' => $user->id,
            'actor_role' => $user->role,
            'action' => 'COURIER_CONFIRM_COD',
            'resource_type' => 'order',
            'resource_id' => $order->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'after_value' => json_encode(['payment_status' => 'paid', 'amount' => $order->total_amount]),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pembayaran COD pesanan berhasil dikonfirmasi.',
            'data' => [
                'order_id' => (string) $order->id,
                'order_number' => $order->order_number,
                'payment_status' => 'PAID',
                'collected_amount' => (float) ($order->total_amount ?? $order->final_price ?? 0),
                'collected_by' => $user->name,
            ],
        ]);
    }
}
