<?php

namespace App\Policies;

use App\Models\Store;
use App\Models\StoreService;
use App\Models\User;

class StoreServicePolicy
{
    /**
     * Determine whether the user can view services of the store.
     */
    public function viewAny(User $user, Store $store): bool
    {
        return $store->owner_user_id === $user->id || $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can create a service for the store.
     */
    public function create(User $user, Store $store): bool
    {
        return $store->owner_user_id === $user->id && $store->approval_status === 'approved';
    }

    /**
     * Determine whether the user can update the service.
     */
    public function update(User $user, StoreService $service): bool
    {
        return $service->store->owner_user_id === $user->id || $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can delete the service.
     */
    public function delete(User $user, StoreService $service): bool
    {
        return $service->store->owner_user_id === $user->id || $user->hasRole('super_admin');
    }
}
