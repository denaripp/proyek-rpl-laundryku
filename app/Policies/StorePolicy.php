<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Store;
use App\Traits\StoreScopeAuthorization;
use Illuminate\Auth\Access\HandlesAuthorization;

class StorePolicy
{
    use HandlesAuthorization, StoreScopeAuthorization;

    /**
     * Determine whether the user can view the store.
     * Platform admin with 'view_store' permission, owner, or staff.
     */
    public function view(?User $user, Store $store): bool
    {
        // Unauthenticated guest browsing marketplace
        if ($user === null) {
            return $store->status === 'approved' && $store->is_active;
        }

        // Platform admin with global permission can view any store.
        if ($user->hasPermissionTo('view_any_store')) {
            return true;
        }

        // Store staff/owner can view their store regardless of status
        if ($this->userHasStoreAccess($user, $store)) {
            return true;
        }

        // Customer with view_store permission can view approved and active stores
        if ($user->hasPermissionTo('view_store')) {
            return $store->status === 'approved' && $store->is_active;
        }

        return false;
    }

    /**
     * SECURITY (Tahap 20): Strict ownership check for OPERATIONS endpoints
     * (orders, documents, staff list, settings). Plain `view_store`
     * permission is NOT sufficient — user must actually own/staff this
     * store. Prevents IDOR where a store_owner/staff from store A could
     * access store B's documents via the generic view_store permission.
     */
    public function viewOperations(User $user, Store $store): bool
    {
        // Platform admin with global permission can operate any store.
        if ($user->hasPermissionTo('view_any_store')) {
            return true;
        }

        // Direct ownership or active staff membership only.
        return $this->userHasStoreAccess($user, $store);
    }

    /**
     * Determine whether the user can update the store.
     * Owner or staff with 'update_store' permission (or platform admin with permission).
     */
    public function update(User $user, Store $store): bool
    {
        // Platform admin with global permission can update any store.
        if ($user->hasPermissionTo('view_any_store')) {
            return true;
        }

        // SECURITY (Tahap 20): ownership FIRST, then permission check.
        // Without this, any store_owner/staff (with update_store permission)
        // could update ANY store, not just the one they own/staff.
        return $this->userHasStoreAccess($user, $store)
            && $user->hasPermissionTo('update_store');
    }

    /**
     * Determine whether the user can delete the store (soft delete).
     * Only owner or platform admin with 'delete_store'.
     */
    public function delete(User $user, Store $store): bool
    {
        // SECURITY (Tahap 20): use hasPermissionTo() (custom permission
        // helper) instead of can() — Gate doesn't auto-delegate snake_case
        // abilities to the custom permission system.
        return $user->hasPermissionTo('delete_store')
            || $user->id === $store->owner_user_id;
    }

    /**
     * List all stores user can see.
     */
    public function viewAny(User $user): bool
    {
        // Platform admin, owner, or staff can list their accessible stores.
        // For platform admin, we allow all if they have permission.
        if ($user->hasPermissionTo('view_any_store')) {
            return true;
        }

        // Otherwise, users with at least one store access can see their own.
        return $user->ownedStores()->exists() || $user->storeStaff()->where('is_active', true)->exists();
    }

    /**
     * Determine whether the user can approve/reject store applications.
     * Only platform admins with 'approve_store' permission.
     */
    public function approve(User $user, Store $store): bool
    {
        return $user->hasPermissionTo('approve_store');
    }
}