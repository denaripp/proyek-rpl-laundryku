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
     * GET /api/orders — list orders scoped by role (customer sees own, store sees own store, admin sees all).
     */
    public function index(Request $request)
    {
        $user = $request->user();
        Gate::forUser($user)->authorize('viewAny', Order::class);

        $query = Order::with(['store:id,name', 'items']);

        if ($user->hasPermissionTo('view_any_order')) {
            // Platform admin: optional filters
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->filled('store_id')) {
                $query->where('store_id', $request->input('store_id'));
            }
        } elseif ($user->hasPermissionTo('view_order')) {
            // Store owner/staff/courier: their store's orders or their own assigned
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
            // Default: own orders only
            $query->where('customer_id', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json($query->latest()->paginate(15));
    }

    /**
     * POST /api/orders — customer creates order.
     */
    public function store(CreateOrderRequest $request)
    {
        $order = $this->orderService->createOrder($request->validated(), $request->user());

        return response()->json($order->load(['items', 'store:id,name']), 201);
    }

    /**
     * GET /api/orders/{order} — show one.
     */
    public function show(Request $request, Order $order)
    {
        Gate::forUser($request->user())->authorize('view', $order);

        return response()->json(
            $order->load(['items', 'store', 'customer:id,name', 'payment', 'review'])
        );
    }

    /**
     * GET /api/orders/{order}/tracking — order tracking timeline.
     */
    public function tracking(Request $request, Order $order)
    {
        Gate::forUser($request->user())->authorize('view', $order);

        return response()->json([
            'order' => $order->only([
                'id', 'order_number', 'status', 'payment_status',
                'pickup_address', 'delivery_address', 'total_amount', 'created_at',
            ]),
            'history' => $order->statusHistories()->latest()->get(),
            'courier_tasks' => $order->courierTasks()->with('courier')->get(),
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
     * POST /api/orders/{order}/cancel — customer/store cancels order.
     */
    public function cancel(Request $request, Order $order)
    {
        Gate::forUser($request->user())->authorize('cancel', $order);

        $request->validate(['reason' => 'nullable|string|max:1000']);

        $updated = $this->orderService->transition(
            $order,
            'cancelled',
            $request->user(),
            $request->input('reason')
        );

        return response()->json($updated);
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
