<?php

use App\Models\Order;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Private tracking channel per order.
// Only the order's customer, or store staff/owner with access to the
// order's store, may subscribe. Everyone else is rejected.
Broadcast::channel('order.{orderId}.tracking', function ($user, int $orderId) {
    $order = Order::find($orderId);

    if (!$order) {
        return false;
    }

    // Customer who owns the order
    if ($user->id === $order->customer_id) {
        return true;
    }

    // Store staff/owner with access to the order's store
    $store = $order->store;
    if ($store) {
        // Direct owner
        if ($user->id === $store->owner_user_id) {
            return true;
        }

        // Active staff member of that store
        $isStaff = \App\Models\StoreStaff::where('store_id', $store->id)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->exists();
        if ($isStaff) {
            return true;
        }
    }

    return false;
});