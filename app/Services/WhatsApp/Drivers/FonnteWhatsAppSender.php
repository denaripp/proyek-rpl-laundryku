<?php

namespace App\Services\WhatsApp\Drivers;

use App\Services\WhatsApp\WhatsAppSender;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FonnteWhatsAppSender implements WhatsAppSender
{
    public function __construct(
        protected ?string $token = null
    ) {
        $this->token = $token ?? config('services.whatsapp.fonnte_token', '');
    }

    public function send(string $to, string $message, array $metadata = []): array
    {
        if (empty($this->token)) {
            Log::warning('[WhatsApp Fonnte] Missing token');
            return [
                'success' => false,
                'error' => 'Fonnte token not configured',
                'payload' => null,
            ];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => $this->token,
            ])->timeout(15)->post('https://api.fonnte.com/send', [
                'target' => $to,
                'message' => $message,
            ]);

            // Token MUST NEVER be logged anywhere
            if ($response->successful()) {
                $data = $response->json();
                $isOk = ($data['status'] ?? false) === true || isset($data['id']);

                if ($isOk) {
                    return [
                        'success' => true,
                        'error' => null,
                        'payload' => $data,
                    ];
                }

                return [
                    'success' => false,
                    'error' => $data['reason'] ?? ($data['detail'] ?? 'Fonnte reported error'),
                    'payload' => $data,
                ];
            }

            Log::error('[WhatsApp Fonnte] HTTP request returned error', [
                'to' => $to,
                'status_code' => $response->status(),
            ]);

            return [
                'success' => false,
                'error' => 'HTTP error ' . $response->status(),
                'payload' => $response->json() ?? ['status_code' => $response->status()],
            ];
        } catch (\Throwable $e) {
            Log::error('[WhatsApp Fonnte] Exception during dispatch', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'payload' => null,
            ];
        }
    }
}
