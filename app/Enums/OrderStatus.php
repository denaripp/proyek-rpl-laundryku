<?php

namespace App\Enums;

enum OrderStatus: string
{
    case CREATED = 'created';
    case WAITING_PICKUP = 'waiting_pickup';
    case COURIER_ASSIGNED = 'courier_assigned';
    case COURIER_TO_CUSTOMER = 'courier_to_customer';
    case PICKED_UP = 'picked_up';
    case IN_TRANSIT_TO_STORE = 'in_transit_to_store';
    case RECEIVED_BY_STORE = 'received_by_store';
    case WEIGHED = 'weighed';
    case PAYMENT_REQUIRED = 'payment_required';
    case PAID = 'paid';
    case PROCESSING = 'processing';
    case READY_FOR_DELIVERY = 'ready_for_delivery';
    case COURIER_ASSIGNED_FOR_DELIVERY = 'courier_assigned_for_delivery';
    case OUT_FOR_DELIVERY = 'out_for_delivery';
    case DELIVERED = 'delivered';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case PICKUP_FAILED = 'pickup_failed';
    case DELIVERY_FAILED = 'delivery_failed';

    public function isCancellable(): bool
    {
        return in_array($this, [
            self::CREATED,
            self::WAITING_PICKUP,
            self::COURIER_ASSIGNED,
            self::COURIER_TO_CUSTOMER,
            self::PICKED_UP,
            self::IN_TRANSIT_TO_STORE,
            self::RECEIVED_BY_STORE,
            self::WEIGHED,
            self::PAYMENT_REQUIRED,
            self::PAID,
            self::PROCESSING,
        ]);
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::CREATED]);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::COMPLETED, self::CANCELLED, self::PICKUP_FAILED, self::DELIVERY_FAILED]);
    }

    public function requiresPayment(): bool
    {
        return in_array($this, [self::PAYMENT_REQUIRED]);
    }

    public function isPaid(): bool
    {
        return $this === self::PAID;
    }

    public function isProcessing(): bool
    {
        return $this === self::PROCESSING;
    }

    public function isDelivered(): bool
    {
        return $this === self::DELIVERED;
    }

    public function isCompleted(): bool
    {
        return $this === self::COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this === self::CANCELLED;
    }

    public function isReadyForDelivery(): bool
    {
        return $this === self::READY_FOR_DELIVERY;
    }

    public function isOutForDelivery(): bool
    {
        return $this === self::OUT_FOR_DELIVERY;
    }

    public function requiresCourierForPickup(): bool
    {
        return in_array($this, [self::WAITING_PICKUP, self::COURIER_ASSIGNED, self::COURIER_TO_CUSTOMER, self::PICKED_UP]);
    }

    public function requiresCourierForDelivery(): bool
    {
        return in_array($this, [self::COURIER_ASSIGNED_FOR_DELIVERY, self::OUT_FOR_DELIVERY]);
    }

    public function isInTransit(): bool
    {
        return in_array($this, [self::IN_TRANSIT_TO_STORE, self::OUT_FOR_DELIVERY]);
    }

    public function isReceivedByStore(): bool
    {
        return $this === self::RECEIVED_BY_STORE;
    }

    public function isWeighed(): bool
    {
        return $this === self::WEIGHED;
    }
}