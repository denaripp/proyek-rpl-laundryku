<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Courier;
use App\Traits\StoreScopeAuthorization;
use Illuminate\Auth\Access\HandlesAuthorization;

class CourierPolicy
{
    use HandlesAuthorization, StoreScopeAuthorization;

    public function view(User $user, Courier $courier): bool
    {
        if ($user->hasPermissionTo('view_courier')) {
            return true;
        }

        // Courier can view their own profile
        if ($user->id === $courier->user_id) {
            return true;
        }

        // Store staff/owner can view couriers assigned to their store
        if ($courier->store_id && $this->userHasStoreAccess($user, $courier->store)) {
            return true;
        }

        return false;
    }

    public function update(User $user, Courier $courier): bool
    {
        if ($user->hasPermissionTo('update_courier')) {
            return true;
        }

        // Courier can update own availability/location (but not all fields)
        if ($user->id === $courier->user_id) {
            return true;
        }

        // Store staff/owner can update courier details for their store
        if ($courier->store_id && $this->userHasStoreAccess($user, $courier->store)) {
            return true;
        }

        return false;
    }

    public function delete(User $user, Courier $courier): bool
    {
        return $user->hasPermissionTo('delete_courier');
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_any_courier') || $this->hasAnyStoreAccess($user);
    }

    private function hasAnyStoreAccess(User $user): bool
    {
        return $user->ownedStores()->exists() || $user->storeStaff()->exists();
    }
}