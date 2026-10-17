<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CourierLocationUpdated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public int $orderId;
    public int $courierId;
    public float $latitude;
    public float $longitude;
    public string $timestamp;

    /**
     * Create a new event instance.
     */
    public function __construct(int $orderId, int $courierId, float $latitude, float $longitude)
    {
        $this->orderId = $orderId;
        $this->courierId = $courierId;
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->timestamp = now()->toISOString();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * Private channel per order, so only the order's customer
     * (and store with access) can subscribe.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('order.' . $this->orderId . '.tracking'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'courier.location.updated';
    }
}