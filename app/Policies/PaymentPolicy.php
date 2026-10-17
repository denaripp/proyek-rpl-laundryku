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
     * Only customer of the order or platform admin.
     */
    public function create(User $user, Order $order): bool
    {
        if ($user->hasPermissionTo('create_payment')) {
            return true;
        }

        // Customer can create payment for their own order
        if ($user->id === $order->customer_id) {
            return true;
        }

        // Store staff/owner with access to the order's store
        if ($order->store && $this->userHasStoreAccess($user, $order->store)) {
            return true;
        }

        return false;
    }
}