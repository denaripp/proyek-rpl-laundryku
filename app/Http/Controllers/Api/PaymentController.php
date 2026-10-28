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

    /**
     * POST /api/v1/orders/{order}/pay & /api/orders/{order}/pay
     * Customer pays order with QRIS, VA, or COD based on store's enabled payment methods.
     */
    public function pay(Request $request, Order $order)
    {
        $user = $request->user();

        // Check order ownership
        if (!$user->hasPermissionTo('create_payment') && $order->customer_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki otorisasi untuk melakukan pembayaran pesanan ini.',
                'error' => [
                    'code' => 'UNAUTHORIZED_PAYMENT',
                    'message' => 'Anda tidak memiliki otorisasi untuk melakukan pembayaran pesanan ini.',
                ],
            ], 403);
        }

        if ($order->payment_status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan sudah berstatus lunas.',
                'error' => [
                    'code' => 'ORDER_ALREADY_PAID',
                    'message' => 'Pesanan sudah berstatus lunas.',
                ],
            ], 422);
        }

        if (!in_array($order->status, ['awaiting_payment', 'payment_required'])) {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan belum atau tidak dalam tahap pembayaran.',
                'error' => [
                    'code' => 'INVALID_ORDER_STATUS',
                    'message' => 'Pesanan belum atau tidak dalam tahap pembayaran.',
                ],
            ], 422);
        }

        $method = strtolower((string) $request->input('method', $request->input('payment_method', '')));

        if (!in_array($method, ['qris', 'va', 'cod'])) {
            return response()->json([
                'success' => false,
                'message' => 'Metode pembayaran tidak valid. Pilih qris, va, atau cod.',
                'error' => [
                    'code' => 'INVALID_PAYMENT_METHOD',
                    'message' => 'Metode pembayaran tidak valid. Pilih qris, va, atau cod.',
                ],
            ], 422);
        }

        $store = $order->store;
        // Server-side amount only: request amount is never used
        $serverAmount = (float) ($order->total_amount ?? $order->final_price ?? $order->total_price ?? $order->estimated_price ?? 0);

        // 1. Cash On Delivery (COD)
        if ($method === 'cod') {
            if (!$store || !$store->cod_enabled) {
                return response()->json([
                    'success' => false,
                    'message' => 'Metode pembayaran COD tidak aktif untuk toko ini.',
                    'error' => [
                        'code' => 'PAYMENT_METHOD_DISABLED',
                        'message' => 'Metode pembayaran COD tidak aktif untuk toko ini.',
                    ],
                ], 422);
            }

            $order->payment_method = 'cod';
            $order->payment_status = 'pending';
            $order->save();

            $payment = Payment::updateOrCreate(
                ['order_id' => $order->id],
                [
                    'payment_method' => 'cod',
                    'amount' => $serverAmount,
                    'status' => 'pending',
                    'payment_reference' => 'COD-' . $order->order_number,
                    'metadata' => [
                        'method' => 'cod',
                        'instructions' => 'Bayar tunai kepada kurir saat cucian diantarkan.',
                    ],
                ]
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'payment_id' => (string) $payment->id,
                    'order_id' => (string) $order->id,
                    'order_number' => $order->order_number,
                    'method' => 'cod',
                    'amount' => $serverAmount,
                    'status' => 'pending',
                    'payment_details' => [
                        'method_label' => 'Bayar di Tempat (COD)',
                        'instructions' => 'Bayar tunai kepada kurir saat cucian diantarkan ke alamat Anda.',
                    ],
                ],
            ]);
        }

        // 2. Online Payment Gateway (QRIS / VA)
        if ($method === 'qris') {
            if (!$store || !$store->qris_enabled) {
                return response()->json([
                    'success' => false,
                    'message' => 'Metode pembayaran QRIS tidak aktif untuk toko ini.',
                    'error' => [
                        'code' => 'PAYMENT_METHOD_DISABLED',
                        'message' => 'Metode pembayaran QRIS tidak aktif untuk toko ini.',
                    ],
                ], 422);
            }

            if (empty(config('xendit.api_key'))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Metode belum tersedia saat ini.',
                    'error' => [
                        'code' => 'GATEWAY_UNAVAILABLE',
                        'message' => 'Metode belum tersedia saat ini.',
                    ],
                ], 422);
            }

            $order->payment_method = 'qris';
            $order->payment_status = 'pending';
            $order->save();

            $customerData = [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? '',
            ];

            try {
                $result = $this->xenditService->createQRCode($order);
            } catch (\Exception $e) {
                try {
                    $result = $this->xenditService->createInvoice($order, 'QRIS', $customerData);
                } catch (\Exception $e2) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Gagal membuat pembayaran QRIS: ' . $e->getMessage(),
                    ], 422);
                }
            }

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        }

        if ($method === 'va') {
            if (!$store || !$store->va_enabled) {
                return response()->json([
                    'success' => false,
                    'message' => 'Metode pembayaran Virtual Account tidak aktif untuk toko ini.',
                    'error' => [
                        'code' => 'PAYMENT_METHOD_DISABLED',
                        'message' => 'Metode pembayaran Virtual Account tidak aktif untuk toko ini.',
                    ],
                ], 422);
            }

            $bankCode = strtoupper((string) $request->input('bank_code', 'BCA'));
            $allowedBanks = $store->va_banks ?? ['BCA', 'BNI', 'BRI', 'MANDIRI', 'PERMATA'];

            if (!in_array($bankCode, $allowedBanks)) {
                return response()->json([
                    'success' => false,
                    'message' => "Bank $bankCode tidak aktif untuk pembayaran di toko ini.",
                    'error' => [
                        'code' => 'VA_BANK_DISABLED',
                        'message' => "Bank $bankCode tidak aktif untuk pembayaran di toko ini.",
                    ],
                ], 422);
            }

            if (empty(config('xendit.api_key'))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Metode belum tersedia saat ini.',
                    'error' => [
                        'code' => 'GATEWAY_UNAVAILABLE',
                        'message' => 'Metode belum tersedia saat ini.',
                    ],
                ], 422);
            }

            $order->payment_method = 'va';
            $order->payment_status = 'pending';
            $order->save();

            $customerData = [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? '',
                'bank_code' => $bankCode,
            ];

            try {
                $result = $this->xenditService->createInvoice($order, 'BANK_TRANSFER', $customerData);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal membuat pembayaran Virtual Account: ' . $e->getMessage(),
                ], 422);
            }

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        }
    }

    /**
     * GET /api/v1/orders/{order}/payment & /api/orders/{order}/payment
     */
    public function showOrderPayment(Request $request, Order $order)
    {
        $user = $request->user();
        Gate::forUser($user)->authorize('view', $order);

        $payment = Payment::where('order_id', $order->id)->latest()->first();

        return response()->json([
            'success' => true,
            'data' => [
                'order_id' => (string) $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'amount' => (float) ($order->total_amount ?? $order->final_price ?? 0),
                'latest_payment' => $payment ? $this->xenditService->formatInvoiceResponse($payment) : null,
            ],
        ]);
    }

    /**
     * POST /api/v1/orders/{order}/payment/simulate-success
     */
    public function simulateSuccess(Request $request, Order $order)
    {
        $user = $request->user();
        if ($user->id !== $order->customer_id && !$user->hasPermissionTo('create_payment')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $order->payment_status = 'paid';
        $order->paid_at = now();
        $order->save();

        $payment = Payment::where('order_id', $order->id)->first();
        if ($payment) {
            $payment->status = 'paid';
            $payment->paid_at = now();
            $payment->save();
        }

        NotificationTrigger::paymentSuccess($order->customer, $order);

        return response()->json([
            'success' => true,
            'data' => [
                'order_id' => (string) $order->id,
                'status' => $order->status,
                'payment_status' => 'PAID',
            ],
        ]);
    }
}