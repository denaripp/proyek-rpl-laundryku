<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Payment;
use App\Models\Order;
use App\Traits\StoreScopeAuthorization;
use Illuminate\Auth\Access\HandlesAuthorization;

class PaymentPolicy
{
    use HandlesAuthorization, StoreScopeAuthorization;

    /**
     * Determine whether the user can view a payment.
     * - Customer: only own order's payment.
     * - Store staff/owner: payments for orders in their store.
     * - Platform admin: if has permission 'view_payment'.
     */
    public function view(User $user, Payment $payment): bool
    {
        // Platform admin with permission
        if ($user->hasPermissionTo('view_payment')) {
            return true;
        }

        // Customer: own order
        $order = $payment->order;
        if ($order && $user->id === $order->customer_id) {
            return true;
        }

        // Store staff/owner: if they have access to the order's store
        if ($order && $this->userHasStoreAccess($user, $order->store)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view any payments (list).
     * Same rules as view, but at collection level.
     */
    public function viewAny(User $user): bool
    {
        // Platform admin with permission
        if ($user->hasPermissionTo('view_any_payment')) {
            return true;
        }

        // Any authenticated user can see their own payments, or store staff can see their store's payments.
        // Filtering will be done at controller level.
        return true;
    }

    /**
     * Determine whether the user can create a payment.
     * Only customer of the order, store owner/staff, or platform admin.
     */
    public function create(User $user, Order $order): bool
    {
        // 1. Platform admin with permission
        if ($user->hasPermissionTo('create_payment')) {
            return true;
        }

        // 2. Strict ownership check for Customer
        if ($user->id === $order->customer_id) {
            return true;
        }

        // 3. Store staff/owner check (ensure store_id is present for IDOR protection)
        if ($order->store_id && $this->userHasStoreAccess($user, $order->store)) {
            return true;
        }

        return false;
    }
}