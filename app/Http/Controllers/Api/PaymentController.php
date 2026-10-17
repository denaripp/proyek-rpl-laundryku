<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Order;
use App\Services\Payment\XenditService;
use App\Notifications\NotificationTrigger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PaymentController extends Controller
{
    protected XenditService $xenditService;

    public function __construct(XenditService $xenditService)
    {
        $this->xenditService = $xenditService;
    }

    /**
     * List payments (filtered by authorization).
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Payment::with('order');

        Gate::forUser($user)->authorize('viewAny', Payment::class);

        if ($user->hasPermissionTo('view_any_payment')) {
            // Platform admin: see all
        } elseif ($user->hasPermissionTo('view_payment')) {
            $query->whereHas('order', function ($q) use ($user) {
                $q->where('customer_id', $user->id);
            });
        } else {
            $query->whereHas('order', function ($q) use ($user) {
                $q->where('customer_id', $user->id);
            });

            $storeIds = $user->ownedStores()->pluck('id')->merge(
                $user->storeStaff()->pluck('store_id')
            )->unique();

            if ($storeIds->isNotEmpty()) {
                $query->orWhereHas('order', function ($q) use ($storeIds) {
                    $q->whereIn('store_id', $storeIds);
                });
            }
        }

        $payments = $query->latest()->paginate(15);

        return response()->json($payments);
    }

    /**
     * Show a single payment.
     */
    public function show(Request $request, Payment $payment)
    {
        $user = $request->user();

        Gate::forUser($user)->authorize('view', $payment);

        return response()->json($payment->load('order'));
    }

    /**
     * Create a new payment (e.g., retry after expiry).
     * Validates order ownership, then calls XenditService::createInvoice().
     */
    public function store(\App\Http\Requests\Payment\CreatePaymentRequest $request)
    {
        $user = $request->user();
        $validated = $request->validated();

        $order = Order::findOrFail($validated['order_id']);

        // Authorize: only customer of order or admin/store staff
        Gate::forUser($user)->authorize('create', [Payment::class, $order]);

        // Check order belongs to current user (customer)
        if (!$user->hasPermissionTo('create_payment') && $order->customer_id !== $user->id) {
            return response()->json(['error' => 'You are not authorized to create payment for this order.'], 403);
        }

        if ($order->payment_status === 'paid') {
            return response()->json(['error' => 'Order is already paid.'], 422);
        }

        $paymentMethod = $validated['payment_method'] ?? 'BANK_TRANSFER';

        $customerData = [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone ?? '',
        ];

        $result = $this->xenditService->createInvoice($order, $paymentMethod, $customerData);

        // Tahap 15: queued side-effect notification
        NotificationTrigger::paymentRequired($order->customer, $order, $result['amount'] ?? $order->total_amount);

        return response()->json($result, 201);
    }
}