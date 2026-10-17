<?php

namespace App\Services\Notification\Channels;

use App\Models\Notification;
use Illuminate\Support\Facades\Log;

class EmailChannel implements NotificationChannelInterface
{
    /**
     * Send Email notification (Log / Dummy provider for dev & testing).
     *
     * TODO: Integrate real Email Provider (e.g. SendGrid, Mailgun, Amazon SES, or SMTP).
     * Requirements for real integration:
     * - Configure SMTP / API credentials in .env and config/mail.php
     * - Create Mailable Blade templates with LaundryKu Navy + Teal theme
     */
    public function send(Notification $notification): bool
    {
        $user = $notification->user;
        $destination = $user?->email ?? $notification->payload['email'] ?? null;

        if (empty($destination)) {
            Log::warning("[EMAIL NOTIFICATION SKIPPED] No email address for user {$notification->user_id}");
            $notification->update(['status' => 'failed']);
            return false;
        }

        // Log dummy dispatch (Phase 13 requirement)
        Log::info(sprintf(
            '[EMAIL NOTIFICATION SENT] To: %s | Event: %s | Subject: %s | Message: %s',
            $destination,
            $notification->event_type,
            $notification->title,
            $notification->message
        ));

        $notification->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        return true;
    }
}
