<?php

namespace App\Services\Notification\Channels;

use App\Models\Notification;

interface NotificationChannelInterface
{
    /**
     * Send notification through the channel.
     * Returns true if sent successfully, false otherwise.
     */
    public function send(Notification $notification): bool;
}
