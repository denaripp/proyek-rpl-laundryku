<?php

namespace App\Services\Refund;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Models\AuditLog;
use App\Services\Payment\XenditService;
use App\Notifications\NotificationTrigger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class RefundService
{
    protected XenditService $xenditService;

    public function __construct(XenditService $xenditService)
    {
        $this->xenditService = $xenditService;
    }

    /**
     * Create a refund request.
     */
    public function requestRefund(Order $order, Payment $payment, float $amount, string $reason, string $method, User $requestedBy): Refund
    {
        // Validate: payment must be PAID
        if ($payment->status !== 'paid') {
            throw new Exception('Refund can only be requested for PAID payments.');
        }

        // Validate: amount cannot exceed paid amount
        if ($amount > $payment->amount) {
            throw new Exception('Refund amount cannot exceed paid amount.');
        }

        // Validate: no existing pending/processing refund for this payment
        $existing = Refund::where('payment_id', $payment->id)
            ->whereIn('status', ['requested', 'processing'])
            ->first();
        if ($existing) {
            throw new Exception('A refund is already in progress for this payment.');
        }

        // Idempotency: generate unique refund reference
        $refundReference = 'REF-' . date('Ymd') . '-' . strtoupper(Str::random(8));

        return DB::transaction(function () use ($order, $payment, $amount, $reason, $method, $requestedBy, $refundReference) {
            $refund = Refund::create([
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'amount' => $amount,
                'status' => 'requested',
                'reason' => $reason,
                'method' => $method,
                'refund_reference' => $refundReference,
                'requested_by' => $requestedBy->id,
                'metadata' => [
                    'requested_at' => now()->toISOString(),
                    'ip' => request()->ip() ?? null,
                ],
            ]);

            // Log audit
            $this->logAudit($requestedBy, 'refund_requested', 'refunds', $refund->id, null, $refund->toArray());

            // Tahap 15: queued side-effect notification (outside transaction semantics)
            NotificationTrigger::refundRequested($order->customer, $order, $refund);

            return $refund;
        });
    }

    /**
     * Process a refund (online via Xendit for online payments, or placeholder for COD).
     */
    public function processRefund(Refund $refund, User $approvedBy): Refund
    {
        if ($refund->status !== 'requested') {
            throw new Exception('Refund can only be processed from REQUESTED status.');
        }

        $payment = $refund->payment;
        if ($payment->status !== 'paid') {
            throw new Exception('Payment is not PAID, cannot refund.');
        }

        $refund->status = 'processing';
        $refund->approved_by = $approvedBy->id;
        $refund->approved_at = now();
        $refund->save();

        $this->logAudit($approvedBy, 'refund_approved', 'refunds', $refund->id, ['old_status' => 'requested'], ['new_status' => 'processing']);

        try {
            if ($payment->payment_method === 'online' && !empty($payment->xendit_invoice_id)) {
                // Online refund via Xendit
                $result = $this->processOnlineRefund($refund);
            } else {
                // COD or other: placeholder
                $result = $this->processCodRefund($refund);
            }

            $refund->status = 'completed';
            $refund->completed_at = now();
            $refund->save();

            $this->logAudit($approvedBy, 'refund_completed', 'refunds', $refund->id, null, ['status' => 'completed']);

            // Tahap 15: queued side-effect notification
            NotificationTrigger::refundCompleted($refund->order->customer, $refund->order, $refund);

            return $refund;

        } catch (Exception $e) {
            $refund->status = 'failed';
            $refund->failed_at = now();
            $refund->failure_reason = $e->getMessage();
            $refund->save();

            $this->logAudit($approvedBy, 'refund_failed', 'refunds', $refund->id, null, ['failure_reason' => $e->getMessage()]);

            throw $e;
        }
    }

    /**
     * Process online refund via Xendit API.
     */
    protected function processOnlineRefund(Refund $refund): array
    {
        $payment = $refund->payment;
        $apiKey = config('xendit.api_key');

        if (empty($apiKey)) {
            throw new Exception('Xendit API key is not configured.');
        }

        // Xendit refund endpoint: POST /v2/refunds
        $payload = [
            'payment_id' => $payment->xendit_payment_id ?? $payment->xendit_invoice_id,
            'amount' => (float) $refund->amount,
            'reason' => $refund->reason,
            'reference' => $refund->refund_reference,
        ];

        $response = Http::withBasicAuth($apiKey, '')
            ->withHeaders(['Idempotency-Key' => $refund->refund_reference])
            ->post('https://api.xendit.co/v2/refunds', $payload);

        if ($response->failed()) {
            // SECURITY (Tahap 19): log status only, NOT response body.
            Log::error('Xendit refund failed', [
                'refund_id' => $refund->id,
                'status' => $response->status(),
            ]);
            throw new Exception('Xendit refund API error: ' . $response->status());
        }

        $data = $response->json();
        $refund->xendit_refund_id = $data['id'] ?? null;
        $refund->metadata = array_merge($refund->metadata ?? [], ['xendit_response' => $data]);
        $refund->save();

        return $data;
    }

    /**
     * Process COD refund (placeholder — manual via bank transfer, etc.).
     */
    protected function processCodRefund(Refund $refund): array
    {
        // Placeholder: in real implementation, this would trigger a manual workflow
        // or bank transfer. For now, we mark it as completed with a note.
        Log::info('COD refund processed (placeholder)', [
            'refund_id' => $refund->id,
            'amount' => $refund->amount,
            'method' => $refund->method,
        ]);

        $refund->metadata = array_merge($refund->metadata ?? [], [
            'cod_processed_at' => now()->toISOString(),
            'note' => 'COD refund processed manually. Actual bank transfer required.',
        ]);
        $refund->save();

        return ['status' => 'ok', 'message' => 'COD refund placeholder processed'];
    }

    /**
     * Cancel a refund request (only if status is REQUESTED).
     */
    public function cancelRefund(Refund $refund, User $actor): Refund
    {
        if ($refund->status !== 'requested') {
            throw new Exception('Only REQUESTED refunds can be cancelled.');
        }

        $refund->status = 'cancelled';
        $refund->save();

        $this->logAudit($actor, 'refund_cancelled', 'refunds', $refund->id, null, ['status' => 'cancelled']);

        return $refund;
    }

    /**
     * Log audit trail.
     */
    protected function logAudit(User $actor, string $action, string $resourceType, int $resourceId, ?array $oldValue, ?array $newValue): void
    {
        AuditLog::create([
            'actor_id' => $actor->id,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'ip_address' => request()->ip() ?? null,
            'user_agent' => request()->userAgent() ?? null,
            'created_at' => now(),
        ]);
    }
}