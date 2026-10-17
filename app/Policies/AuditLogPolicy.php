<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * SECURITY (Tahap 19) — AuditLog access policy.
 *
 * Audit logs are append-only at the application layer:
 *  - Ordinary admin (view_audit, view_admin_dashboard) CANNOT delete.
 *  - Only platform_owner (manage_platform) can purge (e.g., GDPR right-to-erasure
 *    under documented legal request, with audit log of the purge itself).
 *
 * HTTP layer has no DELETE endpoint for audit_logs (defense in depth).
 * This policy catches programmatic deletion attempts via Eloquent.
 */
class AuditLogPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_admin_dashboard');
    }

    public function view(User $user, AuditLog $log): bool
    {
        return $user->hasPermissionTo('view_admin_dashboard');
    }

    /**
     * Only platform_owner can delete audit logs.
     */
    public function delete(User $user, AuditLog $log): bool
    {
        return $user->hasPermissionTo('manage_platform');
    }

    /**
     * No one can bulk delete except platform_owner (rare, audited action).
     */
    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo('manage_platform');
    }
}