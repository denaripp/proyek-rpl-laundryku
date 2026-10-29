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
        $token = $request->header('x-callback-token') ?? $request->header('X-Callback-Token');
        $configToken = config('xendit.webhook_secret') ?? config('xendit.callback_token');

        // Tolak 401 jika kosong, salah, atau token config belum diisi
        if (empty($configToken) || empty($token) || !hash_equals((string) $configToken, (string) $token)) {
            Log::warning('Xendit webhook token verification failed', [
                'token_present' => !empty($token),
                'config_token_present' => !empty($configToken),
            ]);
            return response()->json(['error' => 'Invalid or missing callback token'], 401);
        }

        try {
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
