<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\AssignCourierRequest;
use App\Http\Requests\Order\CreateOrderRequest;
use App\Http\Requests\Order\SetActualWeightRequest;
use App\Http\Requests\Order\UpdateOrderStatusRequest;
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

        $items = $order->items ? $order->items->map(function ($it) {
            return [
                'id' => (string) $it->id,
                'service_id' => (string) ($it->service_id ?? ''),
                'service_name' => $it->service_name ?? ($it->service?->name ?? 'Layanan Laundry'),
                'pricing_unit' => $it->pricing_unit ?? 'kg',
                'unit_price' => (float) ($it->unit_price ?? $it->price_per_unit ?? 0),
                'minimum_charge' => (float) ($it->minimum_charge ?? 0),
                'quantity_estimated' => (float) ($it->quantity_estimated ?? $it->quantity ?? 0),
                'quantity_actual' => $it->quantity_actual !== null ? (float) $it->quantity_actual : null,
                'line_estimated_amount' => (float) ($it->line_estimated_amount ?? $it->subtotal ?? 0),
                'line_final_amount' => $it->line_final_amount !== null ? (float) $it->line_final_amount : null,
            ];
        })->values() : [];

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
            'commission_amount' => (float) ($order->commission_amount ?? 0),
            'pickup_address' => $pickupAddress,
            'delivery_address' => $deliveryAddress,
            'customer_notes' => $order->customer_notes,
            'price_approved_at' => $order->price_approved_at?->toISOString(),
            'cancelled_at' => $order->cancelled_at?->toISOString(),
            'completed_at' => $order->completed_at?->toISOString(),
            'created_at' => $order->created_at?->toISOString() ?? now()->toISOString(),
            'updated_at' => $order->updated_at?->toISOString() ?? now()->toISOString(),
            'payment_expires_at' => $order->payment_expires_at?->toISOString(),
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
        $order = $this->orderService->createOrder($request->validated(), $request->user());

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

        $updated = $this->orderService->transition(
            $order,
            $toStatus,
            $request->user(),
            $request->input('note', $request->input('reason'))
        );

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
