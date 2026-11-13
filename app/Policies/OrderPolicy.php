<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Order;
use App\Traits\StoreScopeAuthorization;
use Illuminate\Auth\Access\HandlesAuthorization;

class OrderPolicy
{
    use HandlesAuthorization, StoreScopeAuthorization;

    /**
     * Customer: only own orders.
     * Store owner/staff: orders belonging to their store.
     * Courier: orders assigned to them.
     * Platform admin: if has permission.
     */
    public function view(User $user, Order $order): bool
    {
        // Platform admin with global permission can view any order.
        if ($user->hasPermissionTo('view_any_order')) {
            return true;
        }

        // Customer: own order
        if ($user->id === $order->customer_id) {
            return true;
        }

        // Courier: order assigned to this courier
        if ($user->id === $order->courier_id) {
            return true;
        }

        // Store staff/owner: if they have access to the order's store
        if ($this->userHasStoreAccess($user, $order->store)) {
            return true;
        }

        return false;
    }

    /**
     * Update order (e.g., change status, assign courier, etc.)
     * Store staff/owner can update orders in their store.
     * Courier can update their own assigned orders (e.g., mark picked up).
     * Customer can cancel (if allowed) but not other updates.
     */
    public function update(User $user, Order $order): bool
    {
        // Platform admin / Super admin has global permission to update any order
        if ($user->hasRole('super_admin') || $user->hasRole('platform_admin') || $user->hasRole('platform_owner')) {
            return true;
        }

        // Platform admin with permission
        if ($user->hasPermissionTo('update_order')) {
            // Mitra store owner/staff/admin can only update orders belonging to their store
            if (($user->hasRole('store_owner') || $user->hasRole('store_staff') || $user->hasRole('store_admin')) && !$user->hasRole('platform_admin') && !$user->hasRole('platform_owner')) {
                return $this->userHasStoreAccess($user, $order->store);
            }

            return true;
        }

        // Customer: only for price approval flow on their own order
        if ($user->id === $order->customer_id && in_array($order->status, ['awaiting_price_approval', 'price_approved'])) {
            return true;
        }

        // Store staff/owner: MUST have access to the order's store
        return $this->userHasStoreAccess($user, $order->store);
    }

    /**
     * Delete order (soft delete) - only platform admin or store owner with permission.
     */
    public function delete(User $user, Order $order): bool
    {
        if ($user->hasPermissionTo('delete_order')) {
            return true;
        }

        return $this->userHasStoreAccess($user, $order->store);
    }

    /**
     * List orders: users can only list orders they have access to.
     */
    public function viewAny(User $user): bool
    {
        // Platform admin with permission
        if ($user->hasPermissionTo('view_any_order')) {
            return true;
        }

        // Any authenticated user can view their own orders (customer), or staff/owner can view store orders.
        return true; // will be filtered in controller by scope.
    }

    /**
     * Cancel order: customer can cancel own order, store staff/owner, or platform admin.
     */
    public function cancel(User $user, Order $order): bool
    {
        // Platform admin with permission
        if ($user->hasPermissionTo('cancel_order')) {
            return true;
        }

        // Customer: own order, only before processing / pickup completion
        if ($user->id === $order->customer_id) {
            $cancellableStatuses = [
                'created',
                'waiting_pickup',
                'pending_pickup',
                'waiting_for_courier',
                'awaiting_payment',
                'awaiting_price_approval',
            ];
            return in_array(strtolower((string) $order->status), $cancellableStatuses, true);
        }

        // Store staff/owner: if they have access to the order's store
        return $this->userHasStoreAccess($user, $order->store);
    }
}