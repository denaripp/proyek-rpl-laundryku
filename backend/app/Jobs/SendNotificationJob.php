<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Services\Notification\Channels\EmailChannel;
use App\Services\Notification\Channels\InAppChannel;
use App\Services\Notification\Channels\NotificationChannelInterface;
use App\Services\Notification\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of attempts for the job.
     */
    public int $tries = 3;

    /**
     * Delete the job if its models no longer exist.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Notification $notification
    ) {}

    /**
     * Execute the job.
     * Guaranteed resilience: Will never throw unhandled exception that breaks application flow.
     */
    public function handle(): void
    {
        try {
            $channel = $this->resolveChannel($this->notification->channel);

            if (!$channel) {
                Log::error("[NOTIFICATION JOB] Unknown channel: {$this->notification->channel}");
                $this->notification->update(['status' => 'failed']);
                return;
            }

            $success = $channel->send($this->notification);

            if (!$success && $this->notification->status !== 'failed') {
                $this->notification->update(['status' => 'failed']);
            }
        } catch (Throwable $e) {
            Log::error("[NOTIFICATION JOB ERROR] Failed to deliver notification {$this->notification->id}: {$e->getMessage()}", [
                'exception' => $e,
                'notification_id' => $this->notification->id,
                'channel' => $this->notification->channel,
            ]);

            $this->notification->update(['status' => 'failed']);
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error("[NOTIFICATION JOB FAILED PERMANENTLY] Notification ID: {$this->notification->id}", [
            'error' => $exception?->getMessage(),
        ]);

        try {
            $this->notification->update(['status' => 'failed']);
        } catch (Throwable) {
            // Ignore DB errors during final fallback
        }
    }

    /**
     * Resolve channel driver instance.
     */
    protected function resolveChannel(string $channel): ?NotificationChannelInterface
    {
        return match (strtolower($channel)) {
            'in_app' => new InAppChannel(),
            'whatsapp' => new WhatsAppChannel(),
            'email' => new EmailChannel(),
            default => null,
        };
    }
}
