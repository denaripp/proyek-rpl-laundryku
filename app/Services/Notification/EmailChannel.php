<?php

namespace App\Services\Notification;

use App\Models\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * MVP email channel. Uses Laravel's mailer (MAIL_MAILER=log by default under
 * Laragon, so nothing external is required in development).
 */
class EmailChannel implements NotificationChannel
{
    public function key(): string
    {
        return 'email';
    }

    public function send(Notification $notification): bool
    {
        $user = $notification->user;

        if (!$user || !$user->email) {
            Log::warning('Notification email skipped: user has no email', [
                'notification_id' => $notification->id,
            ]);
            return false;
        }

        Mail::raw($notification->content, function ($message) use ($user, $notification) {
            $message->to($user->email)
                ->subject($notification->title);
        });

        return true;
    }
}
