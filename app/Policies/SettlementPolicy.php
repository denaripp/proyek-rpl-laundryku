<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Store;
use App\Models\SettlementPeriod;
use App\Traits\StoreScopeAuthorization;
use Illuminate\Auth\Access\HandlesAuthorization;

class SettlementPolicy
{
    use HandlesAuthorization, StoreScopeAuthorization;

    /**
     * View a settlement: platform permission, or owner/staff of the store ONLY.
     * Tenant isolation: a store can never see another store's settlement data.
     */
    public function view(User $user, SettlementPeriod $settlement): bool
    {
        if ($user->hasPermissionTo('view_any_settlement')) {
            return true;
        }

        return $this->userHasStoreAccess($user, $settlement->store);
    }

    /**
     * List settlements (scoped in controller).
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Generate a settlement: platform admin with permission, or store owner/staff.
     * (Controller passes a transient SettlementPeriod with store_id set,
     * so policy resolution lands here — not on StorePolicy.)
     */
    public function create(User $user, SettlementPeriod $settlement): bool
    {
        if ($user->hasPermissionTo('generate_settlement')) {
            return true;
        }

        $store = $settlement->store;

        return $store ? $this->userHasStoreAccess($user, $store) : false;
    }

    /**
     * Verify settlement: finance/platform admin only (human verification).
     */
    public function verify(User $user): bool
    {
        return $user->hasPermissionTo('verify_settlement');
    }
}