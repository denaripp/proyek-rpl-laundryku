<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Notifications\NotificationTrigger;
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

        // Store payment
        $payment = Payment::create([
            'order_id' => $order->id,
            'payment_method' => $paymentMethod,
            'amount' => $order->total_amount,
            'status' => 'pending',
            'xendit_invoice_id' => $data['id'],
            'xendit_payment_id' => null,
            'xendit_payment_method' => null,
            'xendit_payment_channel' => null,
            'payment_reference' => $data['invoice_url'] ?? null,
            'expires_at' => now()->addMinutes($this->expiryMinutes),
            'idempotency_key' => $idempotencyKey,
            'metadata' => ['xendit_response' => $data],
        ]);

        return $this->formatInvoiceResponse($payment, $data);
    }

    /**
     * Format response for frontend.
     */
    protected function formatInvoiceResponse(Payment $payment, ?array $xenditData = null): array
    {
        return [
            'payment_id' => $payment->id,
            'status' => $payment->status,
            'invoice_url' => $xenditData['invoice_url'] ?? $payment->payment_reference,
            'expires_at' => $payment->expires_at->toISOString(),
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
    public function verifyWebhookSignature(string $payload, string $signature, string $webhookId): bool
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

        // Xendit uses HMAC-SHA256 with webhook secret
        $expected = hash_hmac('sha256', $payload . $webhookId, $secret);
        return hash_equals($expected, $signature);
    }

    /**
     * Process webhook and update payment status.
     */
    public function processWebhook(array $data, string $eventType): void
    {
        $xenditInvoiceId = $data['id'] ?? null;
        if (!$xenditInvoiceId) {
            throw new Exception('Missing invoice ID in webhook payload');
        }

        // Find payment by xendit_invoice_id
        $payment = Payment::where('xendit_invoice_id', $xenditInvoiceId)->first();
        if (!$payment) {
            throw new Exception('Payment not found for invoice: ' . $xenditInvoiceId);
        }

        // Idempotency: check if we already processed this webhook event
        $xenditEventId = $data['event_id'] ?? null;
        if ($xenditEventId) {
            $exists = PaymentEvent::where('xendit_event_id', $xenditEventId)
                ->where('payment_id', $payment->id)
                ->exists();
            if ($exists) {
                Log::info('Duplicate webhook ignored', [
                    'payment_id' => $payment->id,
                    'event_id' => $xenditEventId,
                ]);
                return;
            }
        }

        // Record event
        PaymentEvent::create([
            'payment_id' => $payment->id,
            'event_type' => $eventType,
            'xendit_event_id' => $xenditEventId,
            'payload' => $data,
            'occurred_at' => now(),
        ]);

        // Update payment status based on Xendit status
        $xenditStatus = $data['status'] ?? 'PENDING';
        $payment->xendit_webhook_id = $xenditEventId;
        $payment->webhook_received_at = now();

        if ($xenditStatus === 'PAID') {
            // Verify amount matches order
            $paidAmount = (float) ($data['amount'] ?? 0);
            if (abs($paidAmount - $payment->amount) > 0.01) {
                Log::error('Webhook amount mismatch', [
                    'payment_id' => $payment->id,
                    'expected' => $payment->amount,
                    'received' => $paidAmount,
                ]);
                throw new Exception('Amount mismatch: expected ' . $payment->amount . ' got ' . $paidAmount);
            }

            // Check if payment already expired
            if ($payment->expires_at && $payment->expires_at->isPast()) {
                Log::warning('Payment expired but webhook says PAID', [
                    'payment_id' => $payment->id,
                    'expires_at' => $payment->expires_at,
                ]);
                // Reconciliation: log alert and do NOT change status to paid
                // We'll mark as reconciliation_needed in metadata
                $payment->metadata = array_merge($payment->metadata ?? [], [
                    'reconciliation_needed' => true,
                    'reconciliation_note' => 'Late webhook: payment was expired',
                ]);
                $payment->save();
                return;
            }

            $payment->status = 'paid';
            $payment->paid_at = now();
            $payment->xendit_payment_id = $data['payment_id'] ?? null;
            $payment->xendit_payment_method = $data['payment_method'] ?? null;
            $payment->xendit_payment_channel = $data['payment_channel'] ?? null;

            // Update order payment_status
            $order = $payment->order;
            if ($order) {
                $order->payment_status = 'paid';
                $order->paid_at = now();
                $order->save();

                // Tahap 15: queued side-effect notification
                NotificationTrigger::paymentSuccess($order->customer, $order);
            }

        } elseif ($xenditStatus === 'EXPIRED') {
            $payment->status = 'expired';
            if ($payment->order) {
                NotificationTrigger::paymentExpired($payment->order->customer, $payment->order);
            }
        } elseif ($xenditStatus === 'CANCELLED') {
            $payment->status = 'cancelled';
            if ($payment->order) {
                NotificationTrigger::paymentFailed($payment->order->customer, $payment->order);
            }
        } else {
            // PENDING or other status
            $payment->status = strtolower($xenditStatus);
        }

        $payment->save();
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
}