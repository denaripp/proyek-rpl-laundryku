<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Notifications\NotificationTrigger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class XenditService
{
    protected ?string $apiKey;
    protected ?string $apiUrl;
    protected int $expiryMinutes;

    public function __construct()
    {
        $this->apiKey = config('xendit.api_key');
        $this->apiUrl = config('xendit.api_url');
        $this->expiryMinutes = config('xendit.invoice_expiry_minutes', 60);
    }

    /**
     * Create a new Xendit Dynamic QR Code.
     */
    public function createQRCode(Order $order): array
    {
        if (empty($this->apiKey)) {
            throw new Exception('Xendit API key is not configured.');
        }

        $serverAmount = (float) ($order->total_amount ?? $order->final_price ?? $order->total_price ?? $order->estimated_price ?? 0);
        if ($serverAmount <= 0) {
            throw new Exception('Jumlah pembayaran harus lebih besar dari 0.');
        }

        $referenceId = 'order-' . $order->id . '-' . time();

        $payload = [
            'reference_id' => $referenceId,
            'type' => 'DYNAMIC',
            'currency' => 'IDR',
            'amount' => (int) round($serverAmount),
        ];

        $response = Http::withBasicAuth($this->apiKey, '')
            ->withHeaders([
                'api-version' => '2022-07-31',
            ])
            ->post($this->apiUrl . '/qr_codes', $payload);

        if ($response->failed()) {
            Log::error('Xendit create QR code failed', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'response_body' => $response->body(),
            ]);
            throw new Exception('Xendit QR Code API error: ' . $response->status());
        }

        $data = $response->json();
        $qrString = $data['qr_string'] ?? ($data['qr_code'] ?? null);
        $qrId = $data['id'] ?? null;

        $payment = Payment::updateOrCreate(
            ['order_id' => $order->id],
            [
                'payment_method' => 'qris',
                'amount' => $serverAmount,
                'status' => 'pending',
                'xendit_invoice_id' => $qrId,
                'xendit_payment_id' => null,
                'xendit_payment_method' => 'QRIS',
                'xendit_payment_channel' => 'QRIS',
                'payment_reference' => $qrString,
                'expires_at' => now()->addMinutes(2),
                'metadata' => array_merge(['xendit_response' => $data], [
                    'reference_id' => $referenceId,
                    'qr_string' => $qrString,
                    'qr_id' => $qrId,
                    'type' => 'DYNAMIC',
                ]),
            ]
        );

        return [
            'payment_id' => (string) $payment->id,
            'order_id' => (string) $payment->order_id,
            'order_number' => $order->order_number,
            'method' => 'qris',
            'qr_string' => $qrString,
            'amount' => $serverAmount,
            'status' => 'pending',
            'expires_at' => $payment->expires_at?->toISOString() ?? now()->addMinutes(2)->toISOString(),
            'payment_details' => [
                'method_label' => 'QRIS',
                'qr_string' => $qrString,
                'amount' => $serverAmount,
            ],
        ];
    }
    /**
     * Create a new Xendit invoice.
     */
    public function createInvoice(Order $order, string $paymentMethod = 'BANK_TRANSFER', array $customerData = []): array
    {
        if (empty($this->apiKey)) {
            throw new Exception('Xendit API key is not configured.');
        }

        $callbackUrl = config('xendit.callback_url');
        if (empty($callbackUrl)) {
            throw new Exception('Xendit callback URL is not configured.');
        }

        // Generate idempotency key
        $idempotencyKey = 'order_' . $order->id . '_' . Str::random(8);

        // Check if payment already exists with this idempotency key
        $existingPayment = Payment::where('order_id', $order->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
        if ($existingPayment) {
            return $this->formatInvoiceResponse($existingPayment);
        }

        $payload = [
            'external_id' => 'order_' . $order->id . '_' . time(),
            'amount' => (float) $order->total_amount,
            'payer_email' => $customerData['email'] ?? 'customer@example.com',
            'description' => 'Order #' . $order->order_number,
            'invoice_duration' => $this->expiryMinutes * 60, // in seconds
            'currency' => 'IDR',
            'payment_methods' => [strtoupper($paymentMethod)],
            'customer' => [
                'given_names' => $customerData['name'] ?? 'Customer',
                'email' => $customerData['email'] ?? 'customer@example.com',
                'phone' => $customerData['phone'] ?? null,
            ],
            'callback_url' => $callbackUrl,
            'success_redirect_url' => $callbackUrl . '?success=true',
            'failure_redirect_url' => $callbackUrl . '?failure=true',
        ];

        $response = Http::withBasicAuth($this->apiKey, '')
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->post($this->apiUrl . '/v2/invoices', $payload);

        if ($response->failed()) {
            // SECURITY (Tahap 19): log status + id only, NOT full body
            // (Xendit response body may contain payment credentials).
            Log::error('Xendit create invoice failed', [
                'order_id' => $order->id,
                'status' => $response->status(),
            ]);
            throw new Exception('Xendit API error: ' . $response->status());
        }

        $data = $response->json();

        // Store payment (updateOrCreate to handle retries without violating unique order_id)
        $payment = Payment::updateOrCreate(
            ['order_id' => $order->id],
            [
                'payment_method' => $paymentMethod,
                'amount' => $order->total_amount,
                'status' => 'pending',
                'xendit_invoice_id' => $data['id'] ?? null,
                'xendit_payment_id' => null,
                'xendit_payment_method' => null,
                'xendit_payment_channel' => null,
                'payment_reference' => $data['invoice_url'] ?? null,
                'expires_at' => now()->addMinutes($this->expiryMinutes),
                'idempotency_key' => $idempotencyKey,
                'metadata' => array_merge(['xendit_response' => $data], $customerData),
            ]
        );

        return $this->formatInvoiceResponse($payment, $data);
    }

    /**
     * Format response for frontend.
     */
    public function formatInvoiceResponse(Payment $payment, ?array $xenditData = null): array
    {
        $meta = $payment->metadata ?? [];
        $xData = $xenditData ?? ($meta['xendit_response'] ?? []);
        $method = strtolower($payment->payment_method);
        $bankCode = $meta['bank_code'] ?? ($xData['bank_code'] ?? 'BCA');
        $vaNumber = $meta['va_number'] ?? ($xData['va_number'] ?? ($xData['available_banks'][0]['account_number'] ?? ('88000' . str_pad($payment->order_id, 8, '0', STR_PAD_LEFT))));
        $qrString = $meta['qr_string'] ?? ($xData['qr_string'] ?? ($xData['invoice_url'] ?? null));

        return [
            'payment_id' => (string) $payment->id,
            'order_id' => (string) $payment->order_id,
            'order_number' => $payment->order?->order_number,
            'method' => $method,
            'bank_code' => $bankCode,
            'va_number' => $vaNumber,
            'qr_string' => $qrString,
            'amount' => (float) $payment->amount,
            'status' => $payment->status,
            'expires_at' => $payment->expires_at?->toISOString() ?? now()->addHour()->toISOString(),
            'invoice_url' => $xData['invoice_url'] ?? $payment->payment_reference,
            'payment_details' => [
                'method_label' => strtoupper($method),
                'bank_code' => $bankCode,
                'va_number' => $vaNumber,
                'qr_string' => $qrString,
            ],
        ];
    }

    /**
     * Get invoice status from Xendit.
     */
    public function getInvoiceStatus(string $invoiceId): array
    {
        if (empty($this->apiKey)) {
            throw new Exception('Xendit API key is not configured.');
        }

        $response = Http::withBasicAuth($this->apiKey, '')
            ->get($this->apiUrl . '/v2/invoices/' . $invoiceId);

        if ($response->failed()) {
            // SECURITY (Tahap 19): log status only, NOT body.
            Log::error('Xendit get invoice failed', [
                'invoice_id' => $invoiceId,
                'status' => $response->status(),
            ]);
            throw new Exception('Xendit API error: ' . $response->status());
        }

        return $response->json();
    }

    /**
     * Verify webhook signature.
     */
    public function verifyWebhookSignature(string $payload, ?string $signature = null, ?string $webhookId = null): bool
    {
        $secret = config('xendit.webhook_secret');

        if (empty($secret)) {
            if (app()->environment('local', 'testing')) {
                Log::warning('Xendit webhook secret not configured, skipping verification (development mode).');
                return true;
            }

            // Production: fail closed, not fail open
            Log::error('Xendit webhook secret is missing in production environment.');
            throw new \Exception('Webhook secret not configured. Please set XENDIT_WEBHOOK_SECRET in .env.');
        }

        $token = !empty($signature) ? $signature : $payload;
        if (empty($token)) {
            return false;
        }
        return hash_equals((string) $secret, (string) $token);
    }

    /**
     * Process webhook and update payment status.
     */
    public function processWebhook(array $data, string $eventType): void
    {
        $xenditInvoiceId = $data['qr_id'] ?? ($data['data']['qr_id'] ?? ($data['id'] ?? ($data['data']['id'] ?? null)));
        $externalId = $data['reference_id'] ?? ($data['external_id'] ?? ($data['data']['reference_id'] ?? ($data['data']['external_id'] ?? null)));

        if ($xenditInvoiceId) {
            $payment = Payment::where('xendit_invoice_id', $xenditInvoiceId)->first();
        } else {
            $payment = null;
        }

        if (!$payment && !empty($data['id'])) {
            $payment = Payment::where('xendit_invoice_id', $data['id'])->first();
        }

        if (!$payment && !empty($externalId) && preg_match('/order[_-](\d+)/', $externalId, $m)) {
            $candidate = Payment::where('order_id', $m[1])->latest('id')->first();
            // Stale invoice protection: do not match payment if candidate already has a different active invoice_id
            if ($candidate && (empty($candidate->xendit_invoice_id) || $candidate->xendit_invoice_id === $xenditInvoiceId)) {
                $payment = $candidate;
            }
        }

        if (!$payment) {
            throw new Exception('Payment not found for invoice: ' . ($xenditInvoiceId ?? ($externalId ?? 'unknown')));
        }

        DB::transaction(function () use ($payment, $data, $eventType, $xenditInvoiceId) {
            // Lock payment record to prevent concurrent execution races
            $lockedPayment = Payment::where('id', $payment->id)->lockForUpdate()->firstOrFail();

            // Jika order/payment sudah berstatus terminal ('paid', 'refunded', 'cancelled'), return tanpa proses ulang
            $order = $lockedPayment->order;
            if (($order && in_array($order->payment_status, ['paid', 'refunded', 'cancelled']))
                || in_array($lockedPayment->status, ['paid', 'refunded', 'cancelled'])) {
                return;
            }

            // Idempotency: deterministic event ID generation to guard null event_id
            $xenditEventId = $data['event_id'] ?? null;
            if (!$xenditEventId && !empty($xenditInvoiceId)) {
                $rawStatus = $data['status'] ?? ($data['data']['status'] ?? 'UNKNOWN');
                $xenditEventId = 'evt_' . strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $eventType)) . '_' . $xenditInvoiceId . '_' . strtolower((string) $rawStatus);
            }

            if ($xenditEventId) {
                $exists = PaymentEvent::where('xendit_event_id', $xenditEventId)
                    ->where('payment_id', $lockedPayment->id)
                    ->lockForUpdate()
                    ->exists();
                if ($exists) {
                    Log::info('Duplicate webhook ignored', [
                        'payment_id' => $lockedPayment->id,
                        'event_id' => $xenditEventId,
                    ]);
                    return;
                }
            }

            // Record event
            PaymentEvent::create([
                'payment_id' => $lockedPayment->id,
                'event_type' => $eventType,
                'xendit_event_id' => $xenditEventId,
                'payload' => $data,
                'occurred_at' => now(),
            ]);

            // Update payment status based on Xendit status
            $rawStatus = $data['status'] ?? ($data['data']['status'] ?? 'PENDING');
            $xenditStatus = strtoupper((string) $rawStatus);
            $lockedPayment->xendit_webhook_id = $xenditEventId;
            $lockedPayment->webhook_received_at = now();

            if (in_array($xenditStatus, ['PAID', 'COMPLETED', 'SUCCEEDED'])) {
                // Verify amount exists and matches order (do not default to expected amount)
                $rawAmount = $data['amount'] ?? ($data['data']['amount'] ?? null);
                if ($rawAmount === null || !is_numeric($rawAmount) || (float) $rawAmount <= 0) {
                    Log::error('Webhook payload missing valid positive amount', [
                        'payment_id' => $lockedPayment->id,
                        'raw_amount' => $rawAmount,
                    ]);
                    throw new Exception('Webhook payload missing valid positive amount.');
                }
                $paidAmount = (float) $rawAmount;

                $isQr = str_contains(strtolower($eventType), 'qr')
                    || strtolower((string) $lockedPayment->payment_method) === 'qris'
                    || isset($data['qr_id'])
                    || isset($data['data']['qr_id']);

                $orderTotal = (float) ($lockedPayment->order?->total_amount ?? $lockedPayment->amount);

                if ($isQr) {
                    if (abs($paidAmount - $orderTotal) > 0.01) {
                        Log::warning('Webhook amount mismatch for QR payment, not setting paid', [
                            'payment_id' => $lockedPayment->id,
                            'order_id' => $lockedPayment->order_id,
                            'expected' => $orderTotal,
                            'received' => $paidAmount,
                        ]);
                        return;
                    }
                } else {
                    if (abs($paidAmount - $lockedPayment->amount) > 0.01) {
                        Log::error('Webhook amount mismatch', [
                            'payment_id' => $lockedPayment->id,
                            'expected' => $lockedPayment->amount,
                            'received' => $paidAmount,
                        ]);
                        throw new Exception('Amount mismatch: expected ' . $lockedPayment->amount . ' got ' . $paidAmount);
                    }
                }

                // Check if payment already expired
                if ($lockedPayment->expires_at && $lockedPayment->expires_at->isPast()) {
                    Log::warning('Payment expired but webhook says PAID', [
                        'payment_id' => $lockedPayment->id,
                        'expires_at' => $lockedPayment->expires_at,
                    ]);
                    // Reconciliation: log alert and do NOT change status to paid
                    $lockedPayment->metadata = array_merge($lockedPayment->metadata ?? [], [
                        'reconciliation_needed' => true,
                        'reconciliation_note' => 'Late webhook: payment was expired',
                    ]);
                    $lockedPayment->save();
                    return;
                }

                $lockedPayment->status = 'paid';
                $lockedPayment->paid_at = now();
                $lockedPayment->xendit_payment_id = $data['payment_id'] ?? null;
                $lockedPayment->xendit_payment_method = $data['payment_method'] ?? null;
                $lockedPayment->xendit_payment_channel = $data['payment_channel'] ?? null;

                // Update order payment_status
                $order = $lockedPayment->order;
                if ($order) {
                    $this->markOrderAsPaid($order, 'Xendit payment confirmed');

                    // Tahap 15: queued side-effect notification
                    NotificationTrigger::paymentSuccess($order->customer, $order);
                    NotificationTrigger::orderPaidForStore($order);
                }

            } elseif ($xenditStatus === 'EXPIRED') {
                if (!in_array($lockedPayment->status, ['paid', 'refunded'])) {
                    $lockedPayment->status = 'expired';
                    if ($lockedPayment->order) {
                        NotificationTrigger::paymentExpired($lockedPayment->order->customer, $lockedPayment->order);
                    }
                }
            } elseif ($xenditStatus === 'CANCELLED') {
                if (!in_array($lockedPayment->status, ['paid', 'refunded'])) {
                    $lockedPayment->status = 'cancelled';
                    if ($lockedPayment->order) {
                        NotificationTrigger::paymentFailed($lockedPayment->order->customer, $lockedPayment->order);
                    }
                }
            } else {
                // PENDING or other status: do not regress terminal status
                if (!in_array($lockedPayment->status, ['paid', 'refunded', 'expired', 'cancelled'])) {
                    $lockedPayment->status = strtolower($xenditStatus);
                }
            }

            $lockedPayment->save();
        });
    }

    /**
     * Check for expired payments and update status.
     */
    public function expirePendingPayments(): void
    {
        $expired = Payment::where('status', 'pending')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($expired as $payment) {
            $payment->status = 'expired';
            $payment->save();

            PaymentEvent::create([
                'payment_id' => $payment->id,
                'event_type' => 'auto_expired',
                'payload' => ['expired_at' => now()->toISOString()],
                'occurred_at' => now(),
            ]);

            // Tahap 15: queued side-effect notification
            if ($payment->order) {
                NotificationTrigger::paymentExpired($payment->order->customer, $payment->order);
            }
        }
    }

    /**
     * Simulate COD payment verification.
     */
    public function verifyCodPayment(Order $order, Payment $payment, string $verificationMethod, array $evidence): bool
    {
        // Placeholder: actual verification would involve OTP, PIN, or courier confirmation
        // For now, we accept any valid verification method
        $allowedMethods = ['otp', 'pin', 'courier_confirmation', 'customer_confirmation'];
        if (!in_array($verificationMethod, $allowedMethods)) {
            return false;
        }

        // Store evidence
        $payment->metadata = array_merge($payment->metadata ?? [], [
            'cod_verification' => [
                'method' => $verificationMethod,
                'evidence' => $evidence,
                'verified_at' => now()->toISOString(),
            ],
        ]);

        $payment->status = 'paid';
        $payment->paid_at = now();
        $payment->save();

        // Update order
        $order->payment_status = 'paid';
        $order->paid_at = now();
        $order->save();

        PaymentEvent::create([
            'payment_id' => $payment->id,
            'event_type' => 'cod_verified',
            'payload' => ['method' => $verificationMethod, 'evidence' => $evidence],
            'occurred_at' => now(),
        ]);

        // Tahap 15: queued side-effect notifications (COD confirmation flow)
        NotificationTrigger::codConfirmation($order->customer, $order);
        NotificationTrigger::paymentSuccess($order->customer, $order);

        return true;
    }

    /**
     * Mark order as paid and advance status to PAID.
     */
    public function markOrderAsPaid(Order $order, string $note = 'Payment confirmed'): Order
    {
        $order->payment_status = 'paid';
        if (empty($order->paid_at)) {
            $order->paid_at = now();
        }
        $order->saveQuietly();

        if (in_array(strtolower($order->status), ['awaiting_payment', 'payment_required', 'weighed'])) {
            if (strtolower($order->status) === 'awaiting_payment') {
                $order->status = 'payment_required';
                $order->saveQuietly();
            }

            try {
                $orderService = app(\App\Services\Order\OrderService::class);
                $actor = $order->store?->owner
                    ?? ($order->store?->owner_user_id ? \App\Models\User::find($order->store->owner_user_id) : null)
                    ?? \App\Models\User::role('platform_admin')->first();
                $orderService->transition($order, \App\Enums\OrderStatus::PAID->value, $actor, $note);
            } catch (\Throwable $e) {
                Log::warning('markOrderAsPaid transition failed: ' . $e->getMessage(), [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
                $order->status = 'paid';
                $order->saveQuietly();
            }
        }

        return $order;
    }
}
