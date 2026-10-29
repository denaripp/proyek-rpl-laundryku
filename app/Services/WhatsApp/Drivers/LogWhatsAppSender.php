<?php

namespace App\Services\WhatsApp\Drivers;

use App\Services\WhatsApp\WhatsAppSender;
use Illuminate\Support\Facades\Log;

class LogWhatsAppSender implements WhatsAppSender
{
    public function send(string $to, string $message, array $metadata = []): array
    {
        Log::info('[WhatsApp Log Driver] Message sent', [
            'to' => $to,
            'message' => $message,
            'metadata' => $metadata,
        ]);

        return [
            'success' => true,
            'error' => null,
            'payload' => [
                'driver' => 'log',
                'target' => $to,
                'sent_at' => now()->toIso8601String(),
            ],
        ];
    }
}
