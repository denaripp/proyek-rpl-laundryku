<?php

namespace App\Listeners;

use App\Events\OrderFinalPriceUpdatedEvent;
use App\Notifications\NotificationTrigger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class HandleFinalPriceChange implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(OrderFinalPriceUpdatedEvent $event): void
    {
        $order = $event->order;

        NotificationTrigger::finalPriceUpdated(
            $order->customer,
            $order,
            $event->oldPrice,
            $event->newPrice
        );

        Log::info('Order final price updated', [
            'order_id' => $order->id,
            'old_price' => $event->oldPrice,
            'new_price' => $event->newPrice,
        ]);
    }
}
