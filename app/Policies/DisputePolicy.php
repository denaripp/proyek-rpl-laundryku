<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Order;
use App\Models\Dispute;
use App\Traits\StoreScopeAuthorization;
use Illuminate\Auth\Access\HandlesAuthorization;

class DisputePolicy
{
    use HandlesAuthorization, StoreScopeAuthorization;

    /**
     * View a dispute: opener, order's customer, store access, or platform permission.
     */
    public function view(User $user, Dispute $dispute): bool
    {
        if ($user->hasPermissionTo('view_any_dispute')) {
            return true;
        }

        if ($user->id === $dispute->opened_by) {
            return true;
        }

        $order = $dispute->order;
        if ($order && $user->id === $order->customer_id) {
            return true;
        }

        if ($order && $this->userHasStoreAccess($user, $order->store)) {
            return true;
        }

        return false;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Open a dispute on an order: order's customer, store access, or permission.
     */
    public function create(User $user, Order $order): bool
    {
        if ($user->hasPermissionTo('create_dispute')) {
            return true;
        }

        if ($user->id === $order->customer_id) {
            return true;
        }

        return $this->userHasStoreAccess($user, $order->store);
    }

    /**
     * Review/investigate a dispute: authorized reviewer only (permission-based).
     */
    public function review(User $user, Dispute $dispute): bool
    {
        return $user->hasPermissionTo('review_dispute');
    }

    /**
     * Resolve/reject a dispute (compensation is a HUMAN decision):
     * Platform Admin/Owner with explicit permission only.
     */
    public function resolve(User $user, Dispute $dispute): bool
    {
        return $user->hasPermissionTo('resolve_dispute');
    }
}