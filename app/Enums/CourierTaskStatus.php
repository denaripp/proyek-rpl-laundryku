<?php

namespace App\Enums;

enum CourierTaskStatus: string
{
    case ASSIGNED = 'assigned';
    case ACCEPTED = 'accepted';
    case ARRIVED = 'arrived';
    case PICKED_UP = 'picked_up';
    case DELIVERED = 'delivered';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    public function isTerminal(): bool
    {
        return in_array($this, [self::DELIVERED, self::FAILED, self::CANCELLED], true);
    }

    public function isActive(): bool
    {
        return in_array($this, [self::ASSIGNED, self::ACCEPTED, self::ARRIVED, self::PICKED_UP], true);
    }
}
