<?php

namespace App\Services\Notification;

use App\Models\Notification;

/**
 * MVP in-app channel: the Notification row itself IS the delivery.
 */
class InAppChannel implements NotificationChannel
{
    public function key(): string
    {
        return 'in_app';
    }

    public function send(Notification $notification): bool
    {
        // The persisted notification row is the in-app payload; nothing extra to do.
        return $notification->exists;
    }
}
