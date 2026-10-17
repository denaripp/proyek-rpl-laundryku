<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queued notification creation + delivery with exponential backoff retries.
 *
 * A failing notification (e.g. mail server down) NEVER affects the business
 * transaction: after exhausting attempts the failure is logged and the job is
 * released to failed_jobs. Business state is committed before this runs.
 */
class SendUserNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** Exponential backoff: 10s, 30s, 90s, ~4.5m, ~13.5m */
    public function backoff(): array
    {
        return [10, 30, 90, 270, 810];
    }

    public function __construct(
        public int $userId,
        public string $type,
        public string $title,
        public string $content,
        public array $data = []
    ) {
        $this->onQueue('notifications');
    }

    public function handle(NotificationService $service): void
    {
        $user = User::find($this->userId);

        if (!$user) {
            return; // Recipient gone; do not retry.
        }

        $service->notify($user, $this->type, $this->title, $this->content, $this->data);
    }

    public function failed(\Throwable $e): void
    {
        // Final safety net: record the error, never rethrow, never touch orders.
        Log::error('Notification job permanently failed', [
            'user_id' => $this->userId,
            'type'    => $this->type,
            'error'   => $e->getMessage(),
        ]);
    }
}
