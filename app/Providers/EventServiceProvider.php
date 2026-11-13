<?php

namespace App\Providers;

use App\Events\OrderFinalPriceUpdatedEvent;
use App\Events\OrderStatusChangedEvent;
use App\Listeners\HandleFinalPriceChange;
use App\Listeners\SendOrderNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        //
    ];
}
