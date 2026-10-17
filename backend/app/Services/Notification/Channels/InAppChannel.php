<?php

namespace App\Services\Notification\Channels;

use App\Models\Notification;

class InAppChannel implements NotificationChannelInterface
{
    /**
     * In-app notifications are stored directly in the database.
     */
    public function send(Notification $notification): bool
    {
        $notification->update([
            'status' => 'unread',
            'sent_at' => now(),
        ]);

        return true;
    }
}
