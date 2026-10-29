<?php

namespace App\Jobs;

use App\Models\WhatsAppLog;
use App\Services\WhatsApp\WhatsAppSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWhatsAppJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function __construct(
        public string $phone,
        public string $message,
        public string $event,
        public ?int $userId = null,
        public ?string $role = null,
        public array $metadata = []
    ) {
        $this->onQueue('notifications');
    }

    public function handle(WhatsAppSender $sender): void
    {
        $driver = config('services.whatsapp.driver', 'log');

        try {
            $result = $sender->send($this->phone, $this->message, $this->metadata);
            $success = $result['success'] ?? false;

            WhatsAppLog::create([
                'recipient_phone'  => $this->phone,
                'recipient_role'   => $this->role,
                'user_id'          => $this->userId,
                'event'            => $this->event,
                'message'          => $this->message,
                'driver'           => $driver,
                'status'           => $success ? 'sent' : 'failed',
                'error'            => $result['error'] ?? null,
                'response_payload' => $result['payload'] ?? null,
            ]);
        } catch (\Throwable $e) {
            WhatsAppLog::create([
                'recipient_phone'  => $this->phone,
                'recipient_role'   => $this->role,
                'user_id'          => $this->userId,
                'event'            => $this->event,
                'message'          => $this->message,
                'driver'           => $driver,
                'status'           => 'failed',
                'error'            => $e->getMessage(),
                'response_payload' => null,
            ]);

            Log::error('SendWhatsAppJob execution failed', [
                'phone' => $this->phone,
                'event' => $this->event,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SendWhatsAppJob permanently failed', [
            'phone' => $this->phone,
            'event' => $this->event,
            'error' => $e->getMessage(),
        ]);
    }
}
