<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\AssignCourierRequest;
use App\Http\Requests\Order\CreateOrderRequest;
use App\Http\Requests\Order\SetActualWeightRequest;
use App\Http\Requests\Order\UpdateOrderStatusRequest;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Courier\CourierAssignmentService;
use App\Services\Order\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Tahap 16 — Order endpoints (core transaction loop).
 * Controller tipis: validasi FormRequest, Gate::authorize, delegasi ke service.
 */
class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
        protected CourierAssignmentService $courierService,
    ) {}

    /**
     * Format an order model into the canonical OrderDetail contract.
     */
    public function formatOrder(Order $order): array
    {
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
            'payment_failed' => 'PAYMENT_FAILED',
            'payment_expired' => 'PAYMENT_EXPIRED',
        ];

        $paymentStatusMap = [
            'unpaid' => 'UNPAID',
            'pending' => 'UNPAID',
            'paid' => 'PAID',
            'failed' => 'PAYMENT_FAILED',
            'expired' => 'PAYMENT_EXPIRED',
        ];

        $frontendStatus = $statusMap[strtolower($order->status)] ?? strtoupper($order->status);
        $frontendPaymentStatus = $paymentStatusMap[strtolower($order->payment_status ?? 'unpaid')] ?? strtoupper($order->payment_status ?? 'UNPAID');

        $pickupDetail = $order->pickup_address_detail;
        if (!is_array($pickupDetail) && is_string($pickupDetail)) {
            $pickupDetail = json_decode($pickupDetail, true);
        }

        $pickupText = $pickupDetail['address_text'] ?? (is_string($order->pickup_address) && trim($order->pickup_address) !== '' ? $order->pickup_address : null);

        $pickupAddress = [
            'label' => $pickupDetail['label'] ?? 'Alamat Jemput',
            'address_text' => $pickupText ?: 'Alamat tidak tersedia',
            'latitude' => (float) ($pickupDetail['latitude'] ?? $order->pickup_latitude ?? 0),
            'longitude' => (float) ($pickupDetail['longitude'] ?? $order->pickup_longitude ?? 0),
            'recipient_name' => $pickupDetail['recipient_name'] ?? $order->customer?->name,
            'recipient_phone' => $pickupDetail['recipient_phone'] ?? $order->customer?->phone,
        ];

        $deliveryDetail = $order->delivery_address_detail;
        if (!is_array($deliveryDetail) && is_string($deliveryDetail)) {
            $deliveryDetail = json_decode($deliveryDetail, true);
        }

        $deliveryText = $deliveryDetail['address_text'] ?? (is_string($order->delivery_address) && trim($order->delivery_address) !== '' ? $order->delivery_address : null);

        $deliveryAddress = [
            'label' => $deliveryDetail['label'] ?? 'Alamat Antar',
            'address_text' => $deliveryText ?: ($pickupText ?: 'Alamat tidak tersedia'),
            'latitude' => (float) ($deliveryDetail['latitude'] ?? $order->delivery_latitude ?? $order->pickup_latitude ?? 0),
            'longitude' => (float) ($deliveryDetail['longitude'] ?? $order->delivery_longitude ?? $order->pickup_longitude ?? 0),
            'recipient_name' => $deliveryDetail['recipient_name'] ?? $order->customer?->name,
            'recipient_phone' => $deliveryDetail['recipient_phone'] ?? $order->customer?->phone,
        ];

        $items = $order->items ? $order->items->map(function ($it) {
            $unitPrice = (float) ($it->unit_price ?? $it->price_per_unit ?? 0);
            $qty = (float) ($it->quantity_estimated ?? $it->quantity ?? 0);
            $calculatedSubtotal = $unitPrice * $qty;
            if ($it->minimum_charge && $calculatedSubtotal < (float) $it->minimum_charge) {
                $calculatedSubtotal = (float) $it->minimum_charge;
            }
            $subtotal = (float) ($it->total_price ?? ($it->subtotal ?? ($it->line_estimated_amount ?? $calculatedSubtotal)));
            if ($subtotal == 0 && $calculatedSubtotal > 0) {
                $subtotal = $calculatedSubtotal;
            }

            return [
                'id' => (string) $it->id,
                'service_id' => (string) ($it->service_id ?? ''),
                'service_name' => $it->service_name ?? ($it->service?->name ?? 'Layanan Laundry'),
                'pricing_unit' => $it->pricing_unit ?? ($it->unit ?? 'kg'),
                'unit_price' => $unitPrice,
                'minimum_charge' => (float) ($it->minimum_charge ?? 0),
                'quantity_estimated' => $qty,
                'quantity_actual' => $it->quantity_actual !== null ? (float) $it->quantity_actual : null,
                'line_estimated_amount' => $subtotal,
                'subtotal' => $subtotal,
                'total_price' => $subtotal,
                'line_final_amount' => $it->line_final_amount !== null ? (float) $it->line_final_amount : null,
            ];
        })->values() : [];

        $financial = $order->calculateFinancialBreakdown();

        return array_merge($order->toArray(), [
            'id' => (string) $order->id,
            'order_number' => $order->order_number,
            'customer_id' => (string) $order->customer_id,
            'customer_name' => $order->customer?->name,
            'customer_phone' => $order->customer?->phone,
            'store_id' => (string) $order->store_id,
            'store_name' => $order->store?->name,
            'courier_id' => $order->courier_id ? (string) $order->courier_id : null,
            'courier_name' => $order->courier?->name,
            'status' => $frontendStatus,
            'raw_status' => $order->status,
            'payment_status' => $frontendPaymentStatus,
            'estimated_weight_kg' => $order->estimated_weight !== null ? (float) $order->estimated_weight : null,
            'actual_weight_kg' => $order->actual_weight !== null ? (float) $order->actual_weight : null,
            'estimated_amount' => (float) ($order->estimated_price ?? $order->total_amount ?? 0),
            'final_amount' => $order->final_price !== null ? (float) $order->final_price : null,
            'delivery_fee' => (float) ($order->delivery_fee ?? 0),
            'platform_fee' => (float) ($order->platform_fee ?? 0),
            'service_fee' => $financial['service_fee'],
            'is_weighed' => $financial['is_weighed'],
            'commission_rate' => $financial['commission_rate'],
            'commission_amount' => $financial['commission_amount'],
            'commission_configured' => $financial['commission_configured'],
            'store_net_income' => $financial['store_net_income'],
            'pickup_address' => $pickupAddress,
            'delivery_address' => $deliveryAddress,
            'customer_notes' => $order->customer_notes,
            'store_notes' => $order->store_notes ?? $order->statusHistories()->whereIn('status', ['weighed', 'awaiting_price_approval'])->latest()->value('reason'),
            'price_approved_at' => $order->price_approved_at?->toISOString(),
            'cancelled_at' => $order->cancelled_at?->toISOString(),
            'completed_at' => $order->completed_at?->toISOString(),
            'created_at' => $order->created_at?->toISOString() ?? now()->toISOString(),
            'updated_at' => $order->updated_at?->toISOString() ?? now()->toISOString(),
            'payment_expires_at' => $order->payment_expires_at?->toISOString(),
            'store_payment_methods' => [
                'cod' => (bool) ($order->store?->cod_enabled ?? true),
                'qris' => (bool) ($order->store?->qris_enabled ?? false),
                'va' => (bool) ($order->store?->va_enabled ?? false),
                'va_banks' => $order->store?->va_banks ?? ['BCA', 'BNI', 'BRI', 'MANDIRI', 'PERMATA'],
            ],
            'items' => $items,
        ]);
    }

    /**
     * GET /api/v1/orders & /api/orders — list orders scoped by role.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        Gate::forUser($user)->authorize('viewAny', Order::class);

        $query = Order::with(['store:id,name', 'items', 'customer:id,name,phone', 'courier:id,name']);

        if ($user->hasPermissionTo('view_any_order')) {
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->filled('store_id')) {
                $query->where('store_id', $request->input('store_id'));
            }
        } elseif ($user->hasPermissionTo('view_order')) {
            $storeIds = $user->ownedStores()->pluck('id')
                ->merge($user->storeStaff()->where('is_active', true)->pluck('store_id'))
                ->unique();

            $query->where(function ($q) use ($user, $storeIds) {
                $q->where('customer_id', $user->id)
                  ->orWhere('courier_id', $user->id);

                if ($storeIds->isNotEmpty()) {
                    $q->orWhereIn('store_id', $storeIds);
                }
            });
        } else {
            $query->where('customer_id', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = (int) $request->input('per_page', 15);
        $paginator = $query->latest()->paginate($perPage);

        $formattedItems = collect($paginator->items())->map(fn($o) => $this->formatOrder($o))->values();

        return response()->json([
            'success' => true,
            'data' => $formattedItems,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    /**
     * POST /api/v1/orders & /api/orders — customer creates order.
     */
    public function store(CreateOrderRequest $request)
    {
        $data = $request->validated();
        unset($data['is_internal'], $data['internal_order']);

        $order = $this->orderService->createOrder($data, $request->user());

        $order->load(['items', 'store:id,name']);
        $formatted = $this->formatOrder($order);

        return response()->json(array_merge($formatted, [
            'success' => true,
            'data' => $formatted,
        ]), 201);
    }

    /**
     * GET /api/v1/orders/{order} & /api/orders/{order} — show one.
     */
    public function show(Request $request, Order $order)
    {
        Gate::forUser($request->user())->authorize('view', $order);

        $order->load(['items.service', 'store', 'customer:id,name,phone', 'payment', 'review', 'courier:id,name']);
        $formatted = $this->formatOrder($order);

        return response()->json(array_merge($formatted, [
            'success' => true,
            'data' => $formatted,
        ]));
    }

    /**
     * GET /api/v1/orders/{order}/tracking & /api/orders/{order}/tracking
     */
    public function tracking(Request $request, Order $order)
    {
        Gate::forUser($request->user())->authorize('view', $order);

        $order->load(['store:id,name', 'courier:id,name']);
        $statusHistories = $order->statusHistories()->oldest()->get();

        $timeline = $statusHistories->map(function ($h) {
            return [
                'id' => (string) $h->id,
                'from_status' => $h->from_status,
                'to_status' => $h->to_status,
                'actor_role' => $h->actor_role ?? 'system',
                'actor_name' => $h->actor?->name ?? 'System',
                'note' => $h->notes ?? $h->note,
                'timestamp' => $h->created_at?->toISOString() ?? now()->toISOString(),
            ];
        })->values();

        $trackingData = [
            'order_id' => (string) $order->id,
            'order_number' => $order->order_number,
            'current_status' => $order->status,
            'payment_status' => $order->payment_status,
            'store_name' => $order->store?->name,
            'courier_name' => $order->courier?->name,
            'timeline' => $timeline,
        ];

        return response()->json(array_merge($trackingData, [
            'success' => true,
            'data' => $trackingData,
            'order' => $order->only([
                'id', 'order_number', 'status', 'payment_status',
                'pickup_address', 'delivery_address', 'total_amount', 'created_at',
            ]),
            'history' => $order->statusHistories()->latest()->get(),
            'courier_tasks' => $order->courierTasks()->with('courier')->get(),
        ]));
    }

    /**
     * POST /api/v1/orders/{order}/transition — customer/store transitions order.
     */
    public function transition(Request $request, Order $order)
    {
        $toStatus = $request->input('to_status', $request->input('status'));

        if (!$toStatus) {
            return response()->json([
                'success' => false,
                'message' => 'Status transisi wajib diisi.',
                'error' => [
                    'code' => 'INVALID_TRANSITION',
                    'message' => 'Status transisi wajib diisi.',
                ],
            ], 422);
        }

        $normalizedToStatus = strtolower((string) $toStatus);
        $user = $request->user();

        // 1. Mitra tidak diizinkan mengubah status menjadi price_approved atau paid
        $isMitra = $user->hasRole('store_owner')
            || $user->hasRole('store_staff')
            || $user->hasRole('store_admin');

        $isPlatformAdmin = $user->hasRole('platform_admin')
            || $user->hasRole('platform_owner')
            || $user->hasRole('admin');

        if ($isMitra && !$isPlatformAdmin && in_array($normalizedToStatus, ['price_approved', 'paid'])) {
            return response()->json([
                'success' => false,
                'message' => 'Mitra tidak diizinkan mengubah status menjadi price_approved atau paid.',
                'error' => [
                    'code' => 'FORBIDDEN_TRANSITION',
                    'message' => 'Status price_approved hanya dapat disetujui oleh pelanggan pemilik pesanan, dan status paid hanya dapat diubah melalui alur pembayaran.',
                ],
            ], 403);
        }

        // 2. Status 'paid' tidak boleh di-trigger langsung lewat endpoint transition publik selain platform admin
        if ($normalizedToStatus === OrderStatus::PAID->value && !$isPlatformAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Status paid hanya dapat diubah melalui alur pembayaran.',
                'error' => [
                    'code' => 'FORBIDDEN_TRANSITION',
                    'message' => 'Status paid hanya dapat diubah melalui alur pembayaran resmi.',
                ],
            ], 403);
        }

        // 3. Pelanggan pemilik order HANYA boleh melakukan transisi ke price_approved atau cancelled
        $isCustomer = $user->hasRole('customer') && !$isMitra && !$isPlatformAdmin;
        if ($isCustomer) {
            if ($user->id !== $order->customer_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses ke pesanan ini.',
                    'error' => [
                        'code' => 'FORBIDDEN_ORDER_ACCESS',
                        'message' => 'Anda tidak memiliki izin untuk mengubah pesanan milik pengguna lain.',
                    ],
                ], 403);
            }

            if (!in_array($normalizedToStatus, ['price_approved', OrderStatus::CANCELLED->value])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pelanggan hanya dapat menyetujui harga atau membatalkan pesanan.',
                    'error' => [
                        'code' => 'FORBIDDEN_TRANSITION',
                        'message' => 'Pelanggan tidak memiliki izin untuk mengubah status pesanan selain persetujuan harga atau pembatalan.',
                    ],
                ], 403);
            }
        }

        if ($normalizedToStatus === OrderStatus::CANCELLED->value) {
            Gate::forUser($user)->authorize('cancel', $order);
        } else {
            Gate::forUser($user)->authorize('update', $order);
        }

        try {
            // Jika order berada di status weighed dan request menyertakan berat aktual sebelum transisi ke status berikutnya
            $weightInput = $request->input('actual_weight_kg', $request->input('weight', $request->input('actual_weight')));
            if ($weightInput !== null && is_numeric($weightInput) && $order->status === 'weighed') {
                if ($request->filled('note') || $request->filled('store_notes')) {
                    $order->store_notes = $request->input('store_notes', $request->input('note'));
                    $order->save();
                }
                $this->orderService->setActualWeight($order, (float) $weightInput, $request->user());
            }

            $updated = $this->orderService->transition(
                $order,
                $toStatus,
                $request->user(),
                $request->input('note', $request->input('reason'))
            );

            // Simpan berat aktual dan hitung ulang total jika transisi menghasilkan status weighed
            if ($weightInput !== null && is_numeric($weightInput) && $updated->status === 'weighed') {
                if ($request->filled('note') || $request->filled('store_notes')) {
                    $updated->store_notes = $request->input('store_notes', $request->input('note'));
                    $updated->save();
                }
                $this->orderService->setActualWeight($updated, (float) $weightInput, $request->user());
            }

            // Jika status menjadi price_approved, transisi kedua ke awaiting_payment
            // dilakukan langsung di backend tanpa permintaan kedua dari pelanggan
            if ($normalizedToStatus === 'price_approved') {
                $updated = $this->orderService->transition(
                    $updated,
                    'awaiting_payment',
                    $request->user(),
                    'Otomatis lanjut ke tahap pembayaran setelah harga disetujui pelanggan.'
                );
            }
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            throw $e;
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'Invalid transition')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'error' => [
                        'code' => 'INVALID_TRANSITION',
                        'message' => $e->getMessage(),
                    ],
                ], 422);
            }
            throw $e;
        }

        $order->refresh()->load(['items.service', 'store', 'customer']);
        $formatted = $this->formatOrder($order);

        return response()->json([
            'success' => true,
            'data' => $formatted,
        ]);
    }

    /**
     * PATCH /api/orders/{order}/status — store/admin updates order status.
     */
    public function updateStatus(UpdateOrderStatusRequest $request, Order $order)
    {
        Gate::forUser($request->user())->authorize('update', $order);

        $updated = $this->orderService->transition(
            $order,
            $request->input('status'),
            $request->user(),
            $request->input('reason'),
            $request->input('metadata', [])
        );

        return response()->json($updated);
    }

    /**
     * POST /api/v1/orders/{order}/cancel & /api/orders/{order}/cancel
     */
    public function cancel(Request $request, Order $order)
    {
        Gate::forUser($request->user())->authorize('cancel', $order);

        $request->validate(['reason' => 'nullable|string|max:1000']);

        $updated = $this->orderService->transition(
            $order,
            'cancelled',
            $request->user(),
            $request->input('reason', $request->input('note'))
        );

        $order->refresh()->load(['items.service', 'store', 'customer']);
        $formatted = $this->formatOrder($order);

        return response()->json(array_merge($updated->toArray(), [
            'success' => true,
            'data' => $formatted,
        ]));
    }

    /**
     * POST /api/orders/{order}/set-weight — store sets actual weight after weighing.
     */
    public function setWeight(SetActualWeightRequest $request, Order $order)
    {
        Gate::forUser($request->user())->authorize('update', $order);

        // Cannot update weight on completed, delivered, or cancelled orders
        if (in_array(strtolower((string) $order->status), [OrderStatus::COMPLETED->value, OrderStatus::DELIVERED->value, OrderStatus::CANCELLED->value], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Berat aktual tidak dapat diubah pada pesanan yang sudah selesai atau dibatalkan.',
            ], 422);
        }

        $updated = $this->orderService->setActualWeight(
            $order,
            (float) $request->input('weight'),
            $request->user()
        );

        return response()->json($updated);
    }

    /**
     * POST /api/orders/{order}/assign-courier — store/admin assigns courier.
     */
    public function assignCourier(AssignCourierRequest $request, Order $order)
    {
        Gate::forUser($request->user())->authorize('update', $order);

        try {
            $task = $this->courierService->assignCourier($order, $request->input('task_type'));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($task, 201);
    }
}
