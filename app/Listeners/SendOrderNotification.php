<?php

namespace App\Listeners;

use App\Events\OrderStatusChangedEvent;
use App\Notifications\NotificationTrigger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Queued listener: converts order status changes into notification triggers.
 * Delivery itself is handled by SendUserNotificationJob (Tahap 15).
 */
class SendOrderNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(OrderStatusChangedEvent $event): void
    {
        NotificationTrigger::orderStatusChanged(
            $event->order->customer,
            $event->order,
            $event->oldStatus,
            $event->newStatus,
            $event->reason
        );

        if ($event->oldStatus === 'created' && strtolower($event->newStatus) === 'created') {
            NotificationTrigger::newOrderForStore($event->order);
        }
    }
}
