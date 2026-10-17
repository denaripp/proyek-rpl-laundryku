<?php

namespace App\Traits;

use App\Models\Store;
use App\Models\User;

trait StoreScopeAuthorization
{
    /**
     * Check if the user owns the store or is a staff member with active status.
     */
    protected function userHasStoreAccess(User $user, Store $store): bool
    {
        // Direct owner
        if ($user->id === $store->owner_user_id) {
            return true;
        }

        // Staff member (active)
        return $store->staff()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Get the store IDs the user has access to (as owner or active staff).
     */
    protected function userAccessibleStoreIds(User $user): array
    {
        $owned = $user->ownedStores()->pluck('id')->toArray();
        $staffed = \App\Models\StoreStaff::where('user_id', $user->id)
            ->where('is_active', true)
            ->pluck('store_id')
            ->toArray();

        return array_unique(array_merge($owned, $staffed));
    }
}