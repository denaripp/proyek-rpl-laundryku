<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Order;
use App\Models\Review;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReviewPolicy
{
    use HandlesAuthorization;

    /**
     * Customer can review only their own order.
     * (COMPLETED check + 1-review-per-order enforced in ReviewService.)
     */
    public function create(User $user, Order $order): bool
    {
        return $user->id === $order->customer_id;
    }

    /**
     * Moderate/hide reviews: platform admin with permission.
     */
    public function moderate(User $user): bool
    {
        return $user->hasPermissionTo('moderate_review');
    }
}