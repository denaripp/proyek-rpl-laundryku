<?php

namespace App\Services\Notification\Channels;

use App\Models\Notification;
use Illuminate\Support\Facades\Log;

class WhatsAppChannel implements NotificationChannelInterface
{
    /**
     * Send WhatsApp notification (Log / Dummy provider for dev & testing).
     *
     * TODO: Integrate real WhatsApp Gateway Provider (e.g. Fonnte, Twilio, Wablas, or WhatsApp Cloud API).
     * Requirements for real integration:
     * - Configure API Key & Sender Number in config/services.php
     * - Handle HTTP webhook callbacks for delivery status (DLR)
     */
    public function send(Notification $notification): bool
    {
        $user = $notification->user;
        $destination = $user?->phone ?? $notification->payload['phone'] ?? null;

        if (empty($destination)) {
            Log::warning("[WHATSAPP NOTIFICATION SKIPPED] No phone number for user {$notification->user_id}");
            $notification->update(['status' => 'failed']);
            return false;
        }

        // Log dummy dispatch (Phase 13 requirement)
        Log::info(sprintf(
            '[WHATSAPP NOTIFICATION SENT] To: %s | Event: %s | Title: %s | Message: %s',
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
