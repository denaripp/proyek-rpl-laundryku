<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Models\User;
use App\Services\OrderStateMachine;
use App\Services\XenditService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected XenditService $xenditService,
        protected OrderStateMachine $stateMachine
    ) {}

    /**
     * Handle Xendit Payment Webhook Callback (FR-PAY-05, FR-PAY-06).
     */
    public function handleXendit(Request $request): JsonResponse
    {
        // 1. Verify webhook signature / token
        $incomingToken = $request->header('x-callback-token') ?? $request->header('X-CALLBACK-TOKEN');
        if (!$this->xenditService->verifyWebhookToken($incomingToken)) {
            Log::warning('Xendit webhook callback token verification failed.');
            return $this->errorResponse('UNAUTHORIZED', 'Verifikasi signature webhook gagal.', null, 401);
        }

        $payload = $request->all();
        $rawContent = $request->getContent();
        $payloadHash = hash('sha256', $rawContent);

        // 2. Extract unique event identifier
        $eventId = $request->input('id') ??
                   $request->input('data.id') ??
                   $request->input('payment_id') ??
                   $request->input('callback_virtual_account_id') ??
                   ('EVT-' . substr($payloadHash, 0, 16));

        // 3. Prevent duplicate processing (Idempotency check)
        $existingEvent = PaymentWebhookEvent::where('provider_event_id', $eventId)->first();
        if ($existingEvent && $existingEvent->processing_status === 'processed') {
            return response()->json([
                'status' => 'duplicate_ignored',
                'message' => 'Webhook event telah diproses sebelumnya.',
            ], 200);
        }

        // 4. Record event
        $webhookEvent = PaymentWebhookEvent::updateOrCreate(
            ['provider_event_id' => $eventId],
            [
                'provider' => 'xendit',
                'payload_hash' => $payloadHash,
                'processing_status' => 'received',
                'raw_payload' => $payload,
                'received_at' => now(),
            ]
        );

        // 5. Identify Order and Payment from Payload
        // Supported external_id formats: "VA-ORD-XXXX", "QRIS-ORD-XXXX", or direct "ORD-XXXX"
        $externalId = $request->input('external_id') ??
                      $request->input('data.reference_id') ??
                      $request->input('reference_id');

        $orderNumber = null;
        if ($externalId) {
            if (preg_match('/(ORD-\d{8}-[A-Z0-9]+)/', $externalId, $matches)) {
                $orderNumber = $matches[1];
            } else {
                $orderNumber = $externalId;
            }
        }

        $order = null;
        if ($orderNumber) {
            $order = Order::where('order_number', $orderNumber)->orWhere('id', $orderNumber)->first();
        }

        // Fallback: match by payment idempotency_key or provider_reference
        if (!$order && $externalId) {
            $payment = Payment::where('idempotency_key', $externalId)
                ->orWhere('provider_reference', $externalId)
                ->first();
            if ($payment) {
                $order = $payment->order;
            }
        }

        if (!$order) {
            Log::info("Xendit webhook received for unknown order: {$externalId}");
            $webhookEvent->update(['processing_status' => 'ignored']);
            return response()->json([
                'status' => 'ignored',
                'message' => 'Order tidak ditemukan untuk webhook event ini.',
            ], 200);
        }

        // 6. Process Payment Success inside Transaction
        DB::transaction(function () use ($order, $payload, $webhookEvent, $eventId) {
            // Update or create payment record
            $payment = Payment::where('order_id', $order->id)->latest()->first();
            if ($payment) {
                $payment->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                    'provider_reference' => $eventId,
                ]);
            } else {
                $payment = Payment::create([
                    'order_id' => $order->id,
                    'provider' => 'xendit',
                    'method' => 'va',
                    'amount' => $order->final_amount ?: $order->estimated_amount,
                    'status' => 'paid',
                    'idempotency_key' => 'WH-' . $eventId,
                    'provider_reference' => $eventId,
                    'paid_at' => now(),
                ]);
            }

            // If order is waiting for payment, transition to PAID
            if ($order->status === 'AWAITING_PAYMENT') {
                $systemActor = $order->customer ?: User::first();
                $this->stateMachine->transition(
                    $order,
                    'PAID',
                    $systemActor,
                    ['is_system' => true, 'provider_event_id' => $eventId],
                    'Pembayaran berhasil diverifikasi via Webhook Xendit.'
                );
            } else {
                $order->payment_status = 'PAID';
                $order->save();
            }

            // Dispatch multi-channel notification to customer safely (FR-NOTIF)
            try {
                app(\App\Services\Notification\NotificationService::class)->notifyPaymentSuccess($order);
            } catch (\Throwable $e) {
                // Ignore notification failure
            }

            \App\Services\AuditLogService::logPayment(
                $payment,
                'PAYMENT_PAID_WEBHOOK',
                'system',
                ['event_id' => $eventId, 'provider' => 'xendit']
            );

            $webhookEvent->update([
                'processing_status' => 'processed',
                'processed_at' => now(),
            ]);
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Webhook Xendit berhasil diproses.',
        ], 200);
    }
}
