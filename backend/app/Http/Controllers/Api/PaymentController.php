<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderStateMachine;
use App\Services\XenditService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    use ApiResponse;
    public function __construct(
        protected XenditService $xenditService,
        protected OrderStateMachine $stateMachine
    ) {}

    /**
     * Initiate Payment for an Order (FR-PAY-01, FR-PAY-02, FR-PAY-03).
     * Methods supported: 'va', 'qris', 'cod'
     */
    public function pay(Request $request, string $orderId): JsonResponse
    {
        $user = $request->user();
        $order = Order::with(['items', 'store', 'customer'])->find($orderId);

        if (!$order) {
            return $this->errorResponse('NOT_FOUND', 'Pesanan tidak ditemukan.', null, 404);
        }

        // Authorization check
        if ($order->customer_id !== $user->id && !$user->hasRole('super_admin') && !$user->hasRole('store_admin')) {
            return $this->errorResponse('FORBIDDEN', 'Hanya pemilik pesanan yang dapat memproses pembayaran.', null, 403);
        }

        // Validate order status
        if (!in_array($order->status, ['AWAITING_PAYMENT', 'PRICE_APPROVED'])) {
            if ($order->payment_status === 'PAID') {
                return $this->errorResponse(
                    'ALREADY_PAID',
                    'Pesanan ini sudah dibayar lunas.',
                    ['payment_status' => $order->payment_status],
                    409
                );
            }

            return $this->errorResponse(
                'INVALID_STATE',
                "Pembayaran tidak dapat dilakukan pada status '{$order->status}'. Status yang valid: AWAITING_PAYMENT.",
                ['current_status' => $order->status],
                409
            );
        }

        $validated = $request->validate([
            'method' => ['required', 'string', 'in:va,qris,cod,VA,QRIS,COD'],
            'bank_code' => ['nullable', 'string', 'in:BCA,BNI,BRI,MANDIRI,PERMATA,bca,bni,bri,mandiri,permata'],
        ], [
            'method.required' => 'Metode pembayaran wajib dipilih (va, qris, atau cod).',
            'method.in' => 'Metode pembayaran hanya mendukung va (Virtual Account), qris (QRIS), atau cod (Cash on Delivery).',
        ]);

        $method = strtolower($validated['method']);
        $bankCode = strtoupper($validated['bank_code'] ?? 'BCA');
        $amount = (int) ($order->final_amount ?: $order->estimated_amount);

        // 1. COD (Cash On Delivery)
        if ($method === 'cod') {
            $idempotencyKey = 'COD-' . $order->order_number . '-' . $order->id;

            $payment = Payment::updateOrCreate(
                [
                    'order_id' => $order->id,
                    'method' => 'cod',
                ],
                [
                    'provider' => 'cod',
                    'amount' => $amount,
                    'status' => 'pending',
                    'idempotency_key' => $idempotencyKey,
                    'payment_details' => [
                        'method_label' => 'Bayar Tunai di Tempat (COD)',
                        'instructions' => 'Siapkan uang pas sejumlah ' . number_format($amount, 0, ',', '.') . ' saat kurir mengantarkan pakaian bersih ke lokasi Anda.',
                        'collected_by' => 'Kurir Toko',
                    ],
                ]
            );

            // Advance state machine to PAID (with COD notice) so store can immediately begin washing (PROCESSING)
            if ($order->status === 'AWAITING_PAYMENT') {
                $order = $this->stateMachine->transition(
                    $order,
                    'PAID',
                    $user,
                    ['payment_method' => 'cod'],
                    'Pelanggan memilih metode Cash On Delivery (COD). Tagihan akan disetor tunai ke kurir saat pengantaran.'
                );
            }

            \App\Services\AuditLogService::logPayment($payment, 'PAYMENT_INITIATED_COD', $user);

            return $this->successResponse(
                data: [
                    'payment_id' => $payment->id,
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'method' => 'cod',
                    'amount' => $amount,
                    'status' => $payment->status,
                    'order_status' => $order->status,
                    'payment_details' => $payment->payment_details,
                ],
                meta: ['message' => 'Metode COD berhasil dikonfirmasi. Pesanan siap diproses oleh outlet.']
            );
        }

        // 2. Virtual Account via Xendit
        if ($method === 'va') {
            $vaData = $this->xenditService->createVirtualAccount($order, $bankCode, $amount);
            $idempotencyKey = $vaData['external_id'] ?? ('VA-' . $order->order_number);

            $payment = Payment::updateOrCreate(
                [
                    'order_id' => $order->id,
                    'method' => 'va',
                ],
                [
                    'provider' => 'xendit',
                    'amount' => $amount,
                    'status' => 'pending',
                    'idempotency_key' => $idempotencyKey,
                    'payment_details' => $vaData,
                ]
            );

            \App\Services\AuditLogService::logPayment($payment, 'PAYMENT_INITIATED_VA', $user);

            return $this->successResponse(
                data: [
                    'payment_id' => $payment->id,
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'method' => 'va',
                    'bank_code' => $bankCode,
                    'va_number' => $vaData['va_number'],
                    'amount' => $amount,
                    'status' => $payment->status,
                    'expiration_date' => $vaData['expiration_date'] ?? null,
                    'payment_details' => $vaData,
                ],
                meta: ['message' => 'Nomor Virtual Account berhasil dibuat. Silakan lakukan transfer sebelum batas waktu.']
            );
        }

        // 3. QRIS via Xendit
        if ($method === 'qris') {
            $qrisData = $this->xenditService->createQris($order, $amount);
            $idempotencyKey = $qrisData['external_id'] ?? ('QRIS-' . $order->order_number);

            $payment = Payment::updateOrCreate(
                [
                    'order_id' => $order->id,
                    'method' => 'qris',
                ],
                [
                    'provider' => 'xendit',
                    'amount' => $amount,
                    'status' => 'pending',
                    'idempotency_key' => $idempotencyKey,
                    'payment_details' => $qrisData,
                ]
            );

            \App\Services\AuditLogService::logPayment($payment, 'PAYMENT_INITIATED_QRIS', $user);

            return $this->successResponse(
                data: [
                    'payment_id' => $payment->id,
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'method' => 'qris',
                    'qr_string' => $qrisData['qr_string'],
                    'amount' => $amount,
                    'status' => $payment->status,
                    'expires_at' => $qrisData['expires_at'] ?? null,
                    'payment_details' => $qrisData,
                ],
                meta: ['message' => 'QRIS berhasil dibuat. Pindai kode QR menggunakan aplikasi e-wallet / mobile banking Anda.']
            );
        }

        return $this->errorResponse('INVALID_METHOD', 'Metode pembayaran tidak valid.', null, 422);
    }

    /**
     * Get Payment Details for an Order.
     */
    public function show(Request $request, string $orderId): JsonResponse
    {
        $user = $request->user();
        $order = Order::with(['payments'])->find($orderId);

        if (!$order) {
            return $this->errorResponse('NOT_FOUND', 'Pesanan tidak ditemukan.', null, 404);
        }

        $latestPayment = $order->payments->first();

        return $this->successResponse(
            data: [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'amount' => $order->final_amount ?: $order->estimated_amount,
                'latest_payment' => $latestPayment ? [
                    'id' => $latestPayment->id,
                    'provider' => $latestPayment->provider,
                    'method' => $latestPayment->method,
                    'amount' => $latestPayment->amount,
                    'status' => $latestPayment->status,
                    'payment_details' => $latestPayment->payment_details,
                    'paid_at' => $latestPayment->paid_at?->toIso8601String(),
                ] : null,
            ]
        );
    }

    /**
     * Sandbox Payment Simulation Helper (for rapid end-to-end testing).
     */
    public function simulateSuccess(Request $request, string $orderId): JsonResponse
    {
        $user = $request->user();
        $order = Order::find($orderId);

        if (!$order) {
            return $this->errorResponse('NOT_FOUND', 'Pesanan tidak ditemukan.', null, 404);
        }

        // Find or create payment
        $payment = Payment::where('order_id', $order->id)->latest()->first();
        if (!$payment) {
            $payment = Payment::create([
                'order_id' => $order->id,
                'provider' => 'xendit',
                'method' => 'qris',
                'amount' => $order->final_amount ?: $order->estimated_amount,
                'status' => 'paid',
                'idempotency_key' => 'SIM-' . $order->order_number . '-' . time(),
                'paid_at' => now(),
            ]);
        } else {
            $payment->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);
        }

        // Advance Order to PAID if in AWAITING_PAYMENT
        if ($order->status === 'AWAITING_PAYMENT') {
            $order = $this->stateMachine->transition(
                $order,
                'PAID',
                $user,
                ['is_simulation' => true],
                'Simulasi pembayaran berhasil diverifikasi di Sandbox Xendit.'
            );
        } else {
            $order->update(['payment_status' => 'PAID']);
        }

        return $this->successResponse(
            data: [
                'order_id' => $order->id,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'payment' => $payment,
            ],
            meta: ['message' => 'Simulasi pembayaran berhasil. Pesanan kini berstatus Lunas (PAID).']
        );
    }
}
