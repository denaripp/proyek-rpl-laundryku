<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Refund;
use App\Models\Payment;
use App\Traits\StoreScopeAuthorization;
use Illuminate\Auth\Access\HandlesAuthorization;

class RefundPolicy
{
    use HandlesAuthorization, StoreScopeAuthorization;

    /**
     * Determine whether the user can view a refund.
     * - Customer: only own order's refund.
     * - Store staff/owner: refunds for orders in their store.
     * - Platform admin: if has permission 'view_refund'.
     */
    public function view(User $user, Refund $refund): bool
    {
        if ($user->hasPermissionTo('view_refund')) {
            return true;
        }

        $order = $refund->order;
        if ($order && $user->id === $order->customer_id) {
            return true;
        }

        if ($order && $this->userHasStoreAccess($user, $order->store)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view any refunds (list).
     */
    public function viewAny(User $user): bool
    {
        if ($user->hasPermissionTo('view_any_refund')) {
            return true;
        }
        return true;
    }

    /**
     * Determine whether the user can create a refund request.
     * Customer of the order, store staff/owner, or platform admin.
     */
    public function create(User $user, Refund $refund): bool
    {
        if ($user->hasPermissionTo('create_refund')) {
            return true;
        }

        $order = $refund->order;
        if ($order && $user->id === $order->customer_id) {
            return true;
        }

        if ($order && $this->userHasStoreAccess($user, $order->store)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can process a refund (approve/reject).
     * Only platform admin or store owner/staff with permission.
     */
    public function process(User $user, Refund $refund): bool
    {
        if ($user->hasPermissionTo('process_refund')) {
            return true;
        }

        $order = $refund->order;
        if ($order && $this->userHasStoreAccess($user, $order->store)) {
            return true;
        }

        return false;
    }
}