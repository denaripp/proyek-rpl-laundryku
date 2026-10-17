<?php

namespace App\Services\Notification;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Central notification dispatcher (Tahap 15).
 *
 * Design rules:
 *  - Notification delivery is a SIDE EFFECT. This service never throws and
 *    never changes order/transaction state. All failures are logged and
 *    recorded on the notification row.
 *  - Delivery happens in a queued job (SendUserNotificationJob) — never
 *    synchronously from request handling.
 *  - Channels are pluggable: register a new NotificationChannel
 *    implementation here (WhatsApp, push, ...) without touching triggers.
 */
class NotificationService
{
    /** @var array<string, NotificationChannel> keyed by channel key */
    protected array $channels;

    public function __construct(array $channels = null)
    {
        $this->channels = $channels ?? [
            'in_app' => new InAppChannel(),
            'email'  => new EmailChannel(),
        ];
    }

    /**
     * Register an additional channel (e.g. WhatsApp, push).
     */
    public function addChannel(NotificationChannel $channel): void
    {
        $this->channels[$channel->key()] = $channel;
    }

    /**
     * Create + deliver a notification for a user.
     * Called from the queued job — safe to retry, tolerant of failure.
     */
    public function notify(User $user, string $type, string $title, string $content, array $data = []): ?Notification
    {
        try {
            $notification = Notification::create([
                'user_id' => $user->id,
                'type'    => $type,
                'title'   => $title,
                'content' => $content,
                'data'    => $data,
            ]);
        } catch (\Throwable $e) {
            // Even persistence failure must not break the worker / transaction.
            Log::error('Notification could not be persisted', [
                'user_id' => $user->id, 'type' => $type, 'error' => $e->getMessage(),
            ]);
            return null;
        }

        $this->deliver($notification);

        return $notification;
    }

    /**
     * Deliver through every registered channel. Never throws.
     */
    public function deliver(Notification $notification): void
    {
        $delivered = [];

        foreach ($this->channels as $key => $channel) {
            try {
                if ($channel->send($notification)) {
                    $delivered[] = $key;

                    if ($key === 'email') {
                        $notification->email_sent_at = now();
                        $notification->email_error = null;
                    }
                } elseif ($key === 'email') {
                    $notification->email_error = $notification->email_error ?: 'Channel reported failure';
                }
            } catch (\Throwable $e) {
                Log::error('Notification channel failed', [
                    'notification_id' => $notification->id,
                    'channel'         => $key,
                    'error'           => $e->getMessage(),
                ]);

                if ($key === 'email') {
                    $notification->email_error = $e->getMessage();
                }
                // Continue with remaining channels; notification is a side effect.
            }
        }

        $notification->delivered_via = $delivered;
        $notification->save();
    }
}
