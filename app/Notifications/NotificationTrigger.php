<?php

namespace App\Notifications;

use App\Jobs\SendUserNotificationJob;
use App\Models\Notification;
use App\Models\User;

/**
 * Thin trigger API used by services/controllers.
 *
 * All methods only ENQUEUE a queued job — zero synchronous work, zero
 * exceptions escaping to the caller, zero impact on transaction state.
 */
class NotificationTrigger
{
    public static function toUser(?User $user, string $type, string $title, string $content, array $data = []): void
    {
        if (!$user) {
            return;
        }

        try {
            SendUserNotificationJob::dispatch($user->id, $type, $title, $content, $data);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Notification dispatch failed: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'type' => $type,
            ]);
        }
    }

    // ===== Registration =====
    public static function registered(User $user): void
    {
        self::toUser($user, 'registration', 'Welcome to LANDURYKU', 'Your account has been created. Explore laundry stores and place your first order!', ['user_id' => $user->id]);
    }

    // ===== Store lifecycle =====
    public static function storeSubmitted(User $owner, $store): void
    {
        self::toUser($owner, 'store_submitted', 'Store application submitted', "Your store \"{$store->name}\" has been submitted and is awaiting review.", ['store_id' => $store->id]);
    }

    public static function storeApproved(User $owner, $store): void
    {
        self::toUser($owner, 'store_approved', 'Store approved', "Congratulations! Your store \"{$store->name}\" has been approved and is now live.", ['store_id' => $store->id]);
    }

    public static function storeRevisionRequested(User $owner, $store, ?string $reason = null): void
    {
        self::toUser($owner, 'store_revision', 'Revision requested for your store', "Your store \"{$store->name}\" needs revision before it can be approved." . ($reason ? " Reason: {$reason}" : ''), ['store_id' => $store->id, 'reason' => $reason]);
    }

    public static function storeRejected(User $owner, $store, string $reason): void
    {
        self::toUser($owner, 'store_rejected', 'Store application rejected', "Your store application \"{$store->name}\" was rejected. Reason: {$reason}", ['store_id' => $store->id, 'reason' => $reason]);
    }

    // ===== Courier flow =====
    public static function courierAssigned(User $courier, $order, string $taskType): void
    {
        self::toUser($courier, 'courier_assigned', "New {$taskType} task assigned", "You have been assigned a {$taskType} task for order #{$order->order_number}.", ['order_id' => $order->id, 'task_type' => $taskType]);
    }

    public static function courierArriving(User $customer, $order): void
    {
        self::toUser($customer, 'courier_arriving', 'Courier is arriving', "Your courier is on the way for order #{$order->order_number}.", ['order_id' => $order->id]);
    }

    // ===== Order/laundry milestones =====
    public static function pickupCompleted(User $customer, $order): void
    {
        self::toUser($customer, 'pickup_completed', 'Laundry picked up', "Your laundry for order #{$order->order_number} has been picked up and is heading to the store.", ['order_id' => $order->id]);
    }

    public static function laundryReceived(User $customer, $order): void
    {
        self::toUser($customer, 'laundry_received', 'Laundry received by store', "The store has received your laundry for order #{$order->order_number}.", ['order_id' => $order->id]);
    }

    public static function laundryWeighed(User $customer, $order, $weight = null): void
    {
        self::toUser($customer, 'laundry_weighed', 'Laundry weighed', "Your laundry for order #{$order->order_number} has been weighed" . ($weight ? " ({$weight} kg)." : '.') . ' Final price will be updated shortly.', ['order_id' => $order->id, 'weight' => $weight]);
    }

    public static function finalPriceUpdated(User $customer, $order, $oldPrice, $newPrice): void
    {
        self::toUser($customer, 'final_price_updated', 'Final price updated', "Final price for order #{$order->order_number} is " . number_format((float) $newPrice, 2) . '.', ['order_id' => $order->id, 'old_price' => $oldPrice, 'new_price' => $newPrice]);
    }

    public static function processing(User $customer, $order): void
    {
        self::toUser($customer, 'processing', 'Laundry is being processed', "Your laundry for order #{$order->order_number} is being processed.", ['order_id' => $order->id]);
    }

    public static function ready(User $customer, $order): void
    {
        self::toUser($customer, 'ready', 'Laundry is ready', "Your laundry for order #{$order->order_number} is ready for delivery.", ['order_id' => $order->id]);
    }

    public static function deliveryOngoing(User $customer, $order): void
    {
        self::toUser($customer, 'out_for_delivery', 'Laundry on the way', "Your laundry for order #{$order->order_number} is out for delivery.", ['order_id' => $order->id]);
    }

    public static function completed(User $customer, $order): void
    {
        self::toUser($customer, 'completed', 'Order completed', "Order #{$order->order_number} is complete. Thanks for using LANDURYKU!", ['order_id' => $order->id]);
    }

    // ===== Payment =====
    public static function paymentRequired(User $customer, $order, $amount = null): void
    {
        self::toUser($customer, 'payment_required', 'Payment required', "Payment is required for order #{$order->order_number}" . ($amount ? ' (' . number_format((float) $amount, 2) . ')' : '') . '.', ['order_id' => $order->id, 'amount' => $amount]);
    }

    public static function paymentSuccess(User $customer, $order): void
    {
        self::toUser($customer, 'payment_success', 'Payment received', "Your payment for order #{$order->order_number} was successful.", ['order_id' => $order->id]);
    }

    public static function paymentFailed(User $customer, $order): void
    {
        self::toUser($customer, 'payment_failed', 'Payment failed', "Payment for order #{$order->order_number} failed. Please try another method.", ['order_id' => $order->id]);
    }

    public static function paymentExpired(User $customer, $order): void
    {
        self::toUser($customer, 'payment_expired', 'Payment expired', "Payment for order #{$order->order_number} expired. Please create a new payment.", ['order_id' => $order->id]);
    }

    public static function codConfirmation(User $customer, $order): void
    {
        self::toUser($customer, 'cod_confirmation', 'COD payment confirmation needed', "Please confirm the COD payment for order #{$order->order_number}.", ['order_id' => $order->id]);
    }

    // ===== Refund =====
    public static function refundRequested(User $customer, $order, $refund): void
    {
        self::toUser($customer, 'refund_requested', 'Refund requested', "A refund of " . number_format((float) $refund->amount, 2) . " has been requested for order #{$order->order_number}.", ['order_id' => $order->id, 'refund_id' => $refund->id]);
    }

    public static function refundCompleted(User $customer, $order, $refund): void
    {
        self::toUser($customer, 'refund_completed', 'Refund completed', "Your refund of " . number_format((float) $refund->amount, 2) . " for order #{$order->order_number} has been completed.", ['order_id' => $order->id, 'refund_id' => $refund->id]);
    }

    // ===== Dispute =====
    public static function disputeOpened(User $recipient, $order, $dispute): void
    {
        self::toUser($recipient, 'dispute_opened', 'Dispute opened', "A dispute was opened for order #{$order->order_number} (case {$dispute->case_number}).", ['order_id' => $order->id, 'dispute_id' => $dispute->id]);
    }

    public static function disputeResolved(User $recipient, $order, $dispute, string $outcome): void
    {
        self::toUser($recipient, 'dispute_' . $outcome, 'Dispute resolved', "Your dispute for order #{$order->order_number} has been resolved (outcome: {$outcome}).", ['order_id' => $order->id, 'dispute_id' => $dispute->id, 'outcome' => $outcome]);
    }

    // ===== Generic order status (refactored legacy listener entry point) =====
    public static function orderStatusChanged(User $customer, $order, string $oldStatus, string $newStatus, ?string $reason = null): void
    {
        self::toUser($customer, 'order_status', "Order #{$order->order_number} status updated", "Your order status changed from \"{$oldStatus}\" to \"{$newStatus}\"." . ($reason ? " Reason: {$reason}" : ''), ['order_id' => $order->id, 'old_status' => $oldStatus, 'new_status' => $newStatus, 'reason' => $reason]);
    }
}
