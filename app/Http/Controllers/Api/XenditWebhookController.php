<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payment\XenditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class XenditWebhookController extends Controller
{
    protected XenditService $xenditService;

    public function __construct(XenditService $xenditService)
    {
        $this->xenditService = $xenditService;
    }

    public function handle(Request $request)
    {
        try {
            $payload = $request->getContent();
            $signature = $request->header('X-Callback-Token');
            $webhookId = $request->header('X-Webhook-Id');

            // Verify signature (if secret configured)
            if (!$this->xenditService->verifyWebhookSignature($payload, $signature, $webhookId)) {
                Log::warning('Xendit webhook signature verification failed', [
                    'signature' => $signature,
                    'webhook_id' => $webhookId,
                ]);
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            $data = $request->json()->all();
            $eventType = $data['event'] ?? 'unknown';

            Log::info('Xendit webhook received', [
                'event' => $eventType,
                'invoice_id' => $data['id'] ?? null,
            ]);

            $this->xenditService->processWebhook($data, $eventType);

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            // SECURITY (Tahap 19): log message only, no full trace
            // (trace may include request payload with sensitive data).
            Log::error('Xendit webhook error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}