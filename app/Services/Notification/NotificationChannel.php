<?php

namespace App\Services\Notification;

use App\Models\Notification;

/**
 * Channel abstraction for the notification system (Tahap 15).
 *
 * Every delivery channel (in-app, email, WhatsApp, push, ...) implements this
 * interface. Adding a new channel NEVER changes trigger logic: it only needs
 * to be registered in NotificationService::$channels.
 */
interface NotificationChannel
{
    /**
     * Unique channel identifier stored in notifications.delivered_via.
     */
    public function key(): string;

    /**
     * Attempt delivery. Return true when delivered.
     *
     * MUST NOT throw for ordinary delivery failures (mail down, invalid
     * address, ...): return false and let the caller record the error.
     * Throwing is reserved for unexpected/unrecoverable conditions.
     */
    public function send(Notification $notification): bool;
}
