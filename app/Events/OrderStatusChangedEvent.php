<?php

namespace App\Events;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusChangedEvent
{
    use Dispatchable, SerializesModels;

    public Order $order;
    public string $oldStatus;
    public string $newStatus;
    public ?User $actor;
    public ?string $reason;
    public array $metadata;

    public function __construct(
        Order $order,
        string $oldStatus,
        string $newStatus,
        ?User $actor = null,
        ?string $reason = null,
        array $metadata = []
    ) {
        $this->order = $order;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
        $this->actor = $actor;
        $this->reason = $reason;
        $this->metadata = $metadata;
    }
}