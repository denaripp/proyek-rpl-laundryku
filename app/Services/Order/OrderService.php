<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Store;
use App\Models\ServicePrice;
use App\Enums\OrderStatus;
use App\Events\OrderStatusChangedEvent;
use App\Events\OrderFinalPriceUpdatedEvent;
use App\Notifications\NotificationTrigger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Exception;

class OrderService
{
    protected $priceChangeThreshold = 50000; // configurable threshold

    /**
     * Create a new order.
     */
    public function createOrder(array $data, User $customer): Order
    {
        return DB::transaction(function () use ($data, $customer) {
            // Validate store eligibility
            $store = Store::findOrFail($data['store_id']);
            if (!$this->isStoreEligible($store)) {
                throw new Exception('Store is not eligible to receive orders.');
            }

            // Create order
            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'customer_id' => $customer->id,
                'store_id' => $store->id,
                'status' => OrderStatus::CREATED->value,
                'estimated_weight' => $data['estimated_weight'] ?? null,
                'estimated_price' => $data['estimated_price'] ?? null,
                'weight' => $data['estimated_weight'] ?? 0,
                'total_price' => $data['estimated_price'] ?? 0,
                'total_amount' => $data['estimated_price'] ?? 0,
                'payment_status' => 'pending',
                'customer_notes' => $data['customer_notes'] ?? null,
                'order_date' => now(),
            ]);

            // Create order items
            foreach ($data['items'] as $item) {
                $servicePrice = ServicePrice::where('store_id', $store->id)
                    ->where('service_id', $item['service_id'])
                    ->where('is_active', true)
                    ->first();

                if (!$servicePrice) {
                    throw new Exception('Service price not found or inactive.');
                }

                OrderItem::create([
                    'order_id' => $order->id,
                    'service_id' => $item['service_id'],
                    'quantity' => $item['quantity'] ?? 1,
                    'unit' => $servicePrice->unit ?? 'kg',
                    'price_per_unit' => $servicePrice->price_per_unit,
                    'total_price' => $servicePrice->price_per_unit * ($item['quantity'] ?? 1),
                ]);
            }

            // Record initial status history
            $this->recordStatusHistory($order, OrderStatus::CREATED->value, $customer, null, ['note' => 'Order created']);

            // Dispatch event
            try {
                event(new OrderStatusChangedEvent($order, 'created', OrderStatus::CREATED->value, $customer));
            } catch (\Throwable $e) {
                Log::warning('OrderStatusChangedEvent dispatch failed (ignored)', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return $order;
        });
    }

    /**
     * Transition order to a new status.
     */
    public function transition(Order $order, string $newStatus, ?User $actor = null, ?string $reason = null, array $metadata = []): Order
    {
        Gate::forUser($actor)->authorize('update', $order);

        $oldStatus = $order->status;

        // Validate transition
        $this->validateTransition($order, $newStatus);

        return DB::transaction(function () use ($order, $newStatus, $actor, $reason, $metadata, $oldStatus) {
            $order->status = $newStatus;
            $order->save();

            // Record status history
            $this->recordStatusHistory($order, $newStatus, $actor, $reason, $metadata);

            // Dispatch event (drives queued order_status notification via listener)
            try {
                event(new OrderStatusChangedEvent($order, $oldStatus, $newStatus, $actor, $reason, $metadata));
            } catch (\Throwable $e) {
                Log::warning('OrderStatusChangedEvent dispatch failed (ignored)', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Tahap 15: milestone-specific queued notifications (side effects only)
            $this->dispatchMilestoneNotification($order, $newStatus);

            return $order;
        });
    }

    /**
     * Validate status transition.
     */
    protected function validateTransition(Order $order, string $newStatus): void
    {
        $current = $order->status;
        $allowedTransitions = $this->getAllowedTransitions($current);

        if (!in_array($newStatus, $allowedTransitions)) {
            throw new Exception("Invalid transition from {$current} to {$newStatus}.");
        }

        // Additional validation based on status
        if ($newStatus === OrderStatus::CANCELLED->value) {
            $this->validateCancellation($order);
        }

        if ($newStatus === OrderStatus::COMPLETED->value && $order->payment_status !== 'paid') {
            throw new Exception('Order must be paid before completion.');
        }
    }

    /**
     * Get allowed transitions from a given status.
     */
    protected function getAllowedTransitions(string $status): array
    {
        $map = [
            OrderStatus::CREATED->value => [
                OrderStatus::WAITING_PICKUP->value,
                OrderStatus::CANCELLED->value,
            ],
            OrderStatus::WAITING_PICKUP->value => [
                OrderStatus::COURIER_ASSIGNED->value,
                OrderStatus::CANCELLED->value,
            ],
            OrderStatus::COURIER_ASSIGNED->value => [
                OrderStatus::COURIER_TO_CUSTOMER->value,
                OrderStatus::CANCELLED->value,
            ],
            OrderStatus::COURIER_TO_CUSTOMER->value => [
                OrderStatus::PICKED_UP->value,
                OrderStatus::CANCELLED->value,
            ],
            OrderStatus::PICKED_UP->value => [
                OrderStatus::IN_TRANSIT_TO_STORE->value,
                OrderStatus::CANCELLED->value,
            ],
            OrderStatus::IN_TRANSIT_TO_STORE->value => [
                OrderStatus::RECEIVED_BY_STORE->value,
                OrderStatus::CANCELLED->value,
            ],
            OrderStatus::RECEIVED_BY_STORE->value => [
                OrderStatus::WEIGHED->value,
                OrderStatus::CANCELLED->value,
            ],
            OrderStatus::WEIGHED->value => [
                OrderStatus::PAYMENT_REQUIRED->value,
                OrderStatus::CANCELLED->value,
            ],
            OrderStatus::PAYMENT_REQUIRED->value => [
                OrderStatus::PAID->value,
                OrderStatus::CANCELLED->value,
            ],
            OrderStatus::PAID->value => [
                OrderStatus::PROCESSING->value,
                OrderStatus::CANCELLED->value,
            ],
            OrderStatus::PROCESSING->value => [
                OrderStatus::READY_FOR_DELIVERY->value,
                OrderStatus::CANCELLED->value,
            ],
            OrderStatus::READY_FOR_DELIVERY->value => [
                OrderStatus::COURIER_ASSIGNED->value,
                OrderStatus::COURIER_ASSIGNED_FOR_DELIVERY->value,
                OrderStatus::CANCELLED->value,
            ],
            OrderStatus::COURIER_ASSIGNED_FOR_DELIVERY->value => [
                OrderStatus::OUT_FOR_DELIVERY->value,
                OrderStatus::CANCELLED->value,
            ],
            OrderStatus::OUT_FOR_DELIVERY->value => [
                OrderStatus::DELIVERED->value,
                OrderStatus::CANCELLED->value,
            ],
            OrderStatus::DELIVERED->value => [
                OrderStatus::COMPLETED->value,
            ],
            // SECURITY (Tahap 20): failure states must have an exit (otherwise
            // an order stuck in pickup_failed/delivery_failed cannot move).
            // Allowed: CANCELLED (operator override) or retry by going
            // back to WAITING_PICKUP / READY_FOR_DELIVERY respectively.
            OrderStatus::PICKUP_FAILED->value => [
                OrderStatus::WAITING_PICKUP->value,
                OrderStatus::CANCELLED->value,
            ],
            OrderStatus::DELIVERY_FAILED->value => [
                OrderStatus::READY_FOR_DELIVERY->value,
                OrderStatus::CANCELLED->value,
            ],
            OrderStatus::COMPLETED->value => [],
            OrderStatus::CANCELLED->value => [],
        ];

        return $map[$status] ?? [];
    }

    /**
     * Validate cancellation rules.
     */
    protected function validateCancellation(Order $order): void
    {
        // Already cancelled or completed
        if (in_array($order->status, [OrderStatus::CANCELLED->value, OrderStatus::COMPLETED->value])) {
            throw new Exception('Order cannot be cancelled.');
        }

        // If order is in processing or later, cancellation requires special handling
        if ($order->status === OrderStatus::PROCESSING->value ||
            $order->status === OrderStatus::READY_FOR_DELIVERY->value ||
            $order->status === OrderStatus::OUT_FOR_DELIVERY->value) {
            // Allow cancellation but require reason and maybe approval
            if (!$order->cancellation_reason) {
                throw new Exception('Cancellation reason is required for processing orders.');
            }
        }
    }

    /**
     * Set actual weight and recalculate final price.
     */
    public function setActualWeight(Order $order, float $weight, User $actor): Order
    {
        Gate::forUser($actor)->authorize('update', $order);

        if ($order->status !== OrderStatus::WEIGHED->value) {
            throw new Exception('Order must be in WEIGHED status to set actual weight.');
        }

        return DB::transaction(function () use ($order, $weight, $actor) {
            $oldPrice = $order->total_amount;
            $order->actual_weight = $weight;
            $order->save();

            // Recalculate final price
            $this->recalculateFinalPrice($order);

            // Check if price change exceeds threshold
            $threshold = config('order.price_change_threshold', $this->priceChangeThreshold);
            if (abs($order->total_amount - $oldPrice) > $threshold) {
                try {
                    event(new OrderFinalPriceUpdatedEvent($order, $oldPrice, $order->total_amount, $threshold));
                } catch (\Throwable $e) {
                    Log::warning('OrderFinalPriceUpdatedEvent dispatch failed (ignored)', [
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $order;
        });
    }

    /**
     * Recalculate final price server-side.
     */
    public function recalculateFinalPrice(Order $order): void
    {
        $weight = $order->actual_weight ?? $order->estimated_weight ?? 0;

        // Calculate based on order items
        $serviceTotal = $order->items->sum(function ($item) {
            return $item->quantity * $item->price_per_unit;
        });

        // Apply actual weight if available
        if ($order->actual_weight) {
            $serviceTotal = $order->actual_weight * ($order->items->first()->price_per_unit ?? 0);
        }

        $pickupFee = $order->pickup_fee ?? 0;
        $deliveryFee = $order->delivery_fee ?? 0;
        $platformFee = $order->platform_fee ?? 0;
        $discount = $order->discount ?? 0;

        $finalPrice = $serviceTotal + $pickupFee + $deliveryFee + $platformFee - $discount;

        // Ensure price is not negative
        $finalPrice = max(0, $finalPrice);

        $order->total_amount = $finalPrice;
        $order->save();
    }

    /**
     * Tahap 15: milestone-specific queued notifications.
     * Pure side effects — never throws, never affects order state.
     */
    protected function dispatchMilestoneNotification(Order $order, string $newStatus): void
    {
        try {
            $customer = $order->customer;
            if (!$customer) {
                return;
            }

            switch ($newStatus) {
                case OrderStatus::RECEIVED_BY_STORE->value:
                    NotificationTrigger::laundryReceived($customer, $order);
                    break;
                case OrderStatus::WEIGHED->value:
                    NotificationTrigger::laundryWeighed($customer, $order, $order->actual_weight);
                    break;
                case OrderStatus::PAYMENT_REQUIRED->value:
                    NotificationTrigger::paymentRequired($customer, $order, $order->total_amount);
                    break;
                case OrderStatus::PROCESSING->value:
                    NotificationTrigger::processing($customer, $order);
                    break;
                case OrderStatus::READY_FOR_DELIVERY->value:
                    NotificationTrigger::ready($customer, $order);
                    break;
                case OrderStatus::OUT_FOR_DELIVERY->value:
                    NotificationTrigger::deliveryOngoing($customer, $order);
                    break;
                case OrderStatus::COMPLETED->value:
                    NotificationTrigger::completed($customer, $order);
                    break;
            }
        } catch (\Throwable $e) {
            Log::warning('Milestone notification trigger failed (ignored)', [
                'order_id' => $order->id,
                'status' => $newStatus,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Record status history.
     */
    protected function recordStatusHistory(Order $order, string $status, ?User $actor, ?string $reason, array $metadata = []): void
    {
        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => $status,
            'previous_status' => $order->getOriginal('status') ?? 'created',
            'actor_id' => $actor?->id,
            'actor_role' => $actor?->roles()->first()->name ?? null,
            'reason' => $reason,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Generate unique order number.
     */
    protected function generateOrderNumber(): string
    {
        return 'ORD-' . date('Ymd') . '-' . strtoupper(uniqid());
    }

    /**
     * Check if store is eligible to receive orders.
     */
    protected function isStoreEligible(Store $store): bool
    {
        return $store->status === 'approved'
            && $store->is_active
            && $store->servicePrices()->where('is_active', true)->exists()
            && $store->service_radius_km !== null;
    }

    /**
     * Cancel order.
     */
    public function cancel(Order $order, User $actor, string $reason): Order
    {
        Gate::forUser($actor)->authorize('cancel', $order);

        // Validate cancellation
        $this->validateCancellation($order);

        return DB::transaction(function () use ($order, $actor, $reason) {
            $oldStatus = $order->status;
            $order->status = OrderStatus::CANCELLED->value;
            $order->cancelled_at = now();
            $order->cancelled_by = $actor->id;
            $order->cancellation_reason = $reason;
            $order->save();

            // Record history
            $this->recordStatusHistory($order, OrderStatus::CANCELLED->value, $actor, $reason);

            // Trigger refund if already paid
            if ($order->payment_status === 'paid') {
                // Dispatch refund event or handle manually
                // @TODO: Implement refund logic
                Log::info('Order cancelled after payment, refund needed', [
                    'order_id' => $order->id,
                    'amount' => $order->total_amount,
                ]);
            }

            try {
                event(new OrderStatusChangedEvent($order, $oldStatus, OrderStatus::CANCELLED->value, $actor, $reason));
            } catch (\Throwable $e) {
                Log::warning('OrderStatusChangedEvent dispatch failed (ignored)', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return $order;
        });
    }
}