<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\Refund\RefundService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RefundController extends Controller
{
    protected RefundService $refundService;

    public function __construct(RefundService $refundService)
    {
        $this->refundService = $refundService;
    }

    /**
     * List refunds (filtered by authorization).
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Refund::with(['order', 'payment', 'requestedBy']);

        Gate::forUser($user)->authorize('viewAny', Refund::class);

        // Apply filters based on role
        if ($user->hasPermissionTo('view_any_refund')) {
            // Platform admin: see all
        } else {
            // Customer: only their own orders
            $query->whereHas('order', function ($q) use ($user) {
                $q->where('customer_id', $user->id);
            });

            // Store staff/owner: also see orders in their stores
            $storeIds = $user->ownedStores()->pluck('id')->merge(
                $user->storeStaff()->pluck('store_id')
            )->unique();

            if ($storeIds->isNotEmpty()) {
                $query->orWhereHas('order', function ($q) use ($storeIds) {
                    $q->whereIn('store_id', $storeIds);
                });
            }
        }

        $refunds = $query->latest()->paginate(15);

        return response()->json($refunds);
    }

    /**
     * Show a single refund.
     */
    public function show(Request $request, Refund $refund)
    {
        $user = $request->user();
        Gate::forUser($user)->authorize('view', $refund);

        return response()->json($refund->load(['order', 'payment', 'requestedBy', 'approvedBy']));
    }

    /**
     * Request a refund.
     */
    public function store(\App\Http\Requests\Refund\CreateRefundRequest $request)
    {
        $user = $request->user();
        $validated = $request->validated();

        $order = Order::findOrFail($validated['order_id']);
        $payment = Payment::findOrFail($validated['payment_id']);

        // Create a temporary refund object for policy check
        $refund = new Refund([
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'amount' => $validated['amount'],
            'reason' => $validated['reason'],
            'method' => $validated['method'],
        ]);

        Gate::forUser($user)->authorize('create', $refund);

        $result = $this->refundService->requestRefund(
            $order,
            $payment,
            (float) $validated['amount'],
            $validated['reason'],
            $validated['method'],
            $user
        );

        return response()->json($result, 201);
    }

    /**
     * Process a refund (approve and execute).
     */
    public function process(Request $request, Refund $refund)
    {
        $user = $request->user();

        Gate::forUser($user)->authorize('process', $refund);

        $result = $this->refundService->processRefund($refund, $user);

        return response()->json($result);
    }

    /**
     * Cancel a refund request.
     */
    public function cancel(Request $request, Refund $refund)
    {
        $user = $request->user();

        Gate::forUser($user)->authorize('process', $refund);

        $result = $this->refundService->cancelRefund($refund, $user);

        return response()->json($result);
    }
}