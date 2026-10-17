<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderFinalPriceUpdatedEvent
{
    use Dispatchable, SerializesModels;

    public Order $order;
    public float $oldPrice;
    public float $newPrice;
    public float $threshold;

    public function __construct(Order $order, float $oldPrice, float $newPrice, float $threshold)
    {
        $this->order = $order;
        $this->oldPrice = $oldPrice;
        $this->newPrice = $newPrice;
        $this->threshold = $threshold;
    }
}