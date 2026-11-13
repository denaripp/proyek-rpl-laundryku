<?php

namespace App\Services;

use App\Models\Store;
use App\Models\StoreApplication;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\NotificationTrigger;
use App\Enums\StoreStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;
use Exception;

class StoreService
{
    /**
     * Submit store application for review.
     * Transitions: DRAFT → SUBMITTED
     * Validates that required fields are filled.
     */
    public function submit(Store $store, array $data = []): Store
    {
        $this->validateSubmissionData($store, $data);

        return DB::transaction(function () use ($store) {
            $oldStatus = $store->status;

            // Update store fields if provided (only allowed in draft)
            if ($store->status !== StoreStatus::DRAFT->value) {
                throw new Exception('Store can only be submitted from DRAFT status.');
            }

            // Ensure all required fields are present
            $this->ensureRequiredFields($store);

            // Update store status
            $store->status = StoreStatus::SUBMITTED->value;
            $store->save();

            // Create or update application record
            $application = $store->application ?? new StoreApplication(['store_id' => $store->id]);
            $application->submitted_at = now();
            $application->save();

            // Log audit
            $this->logAudit($store, 'store_submitted', $oldStatus, $store->status, [
                'application_id' => $application->id,
            ]);

            // Notify applicant
            $this->notifyUser($store->owner, 'Store submitted for review', "Your store '{$store->name}' has been submitted for review.");

            return $store;
        });
    }

    /**
     * Review store application (admin action).
     * Transitions: SUBMITTED → UNDER_REVIEW
     */
    public function startReview(Store $store, User $reviewer): Store
    {
        Gate::forUser($reviewer)->authorize('approve', $store);

        return DB::transaction(function () use ($store, $reviewer) {
            $oldStatus = $store->status;

            if (!in_array($store->status, [StoreStatus::SUBMITTED->value, StoreStatus::RESUBMITTED->value])) {
                throw new Exception('Store must be SUBMITTED or RESUBMITTED to start review.');
            }

            $store->status = StoreStatus::UNDER_REVIEW->value;
            $store->save();

            $application = $store->application ?? new StoreApplication(['store_id' => $store->id]);
            $application->reviewed_by = $reviewer->id;
            $application->reviewed_at = now();
            $application->save();

            $this->logAudit($store, 'store_review_started', $oldStatus, $store->status, [
                'reviewer_id' => $reviewer->id,
            ]);

            $this->notifyUser($store->owner, 'Store under review', "Your store '{$store->name}' is now under review.");

            return $store;
        });
    }

    /**
     * Approve store application.
     * Transitions: UNDER_REVIEW → APPROVED
     */
    public function approve(Store $store, User $reviewer, ?string $note = null): Store
    {
        Gate::forUser($reviewer)->authorize('approve', $store);

        return DB::transaction(function () use ($store, $reviewer, $note) {
            $oldStatus = $store->status;

            if ($store->status !== StoreStatus::UNDER_REVIEW->value) {
                throw new Exception('Store must be UNDER_REVIEW to approve.');
            }

            $store->status = StoreStatus::APPROVED->value;
            $store->is_active = true;
            $store->approved_at = now();
            $store->save();

            // Ensure application exists
            $application = $store->application ?? new StoreApplication(['store_id' => $store->id]);
            $application->approved_at = now();
            $application->review_notes = $note ?? $application->review_notes;
            $application->save();

            $this->logAudit($store, 'store_approved', $oldStatus, $store->status, [
                'reviewer_id' => $reviewer->id,
                'note' => $note,
            ]);

            $this->notifyUser($store->owner, 'Store approved', "Your store '{$store->name}' has been approved.");

            return $store;
        });
    }

    /**
     * Auto-approve store application if eligible criteria are met.
     * Transitions: DRAFT / SUBMITTED / RESUBMITTED / UNDER_REVIEW → APPROVED
     */
    public function autoApprove(Store $store, array $metadata = []): Store
    {
        return DB::transaction(function () use ($store, $metadata) {
            $oldStatus = $store->status;

            $store->status = StoreStatus::APPROVED->value;
            $store->is_active = true;
            $store->approved_at = now();
            $store->save();

            // Ensure application exists
            $application = $store->application ?? new StoreApplication(['store_id' => $store->id]);
            $application->submitted_at = $application->submitted_at ?? now();
            $application->approved_at = now();
            $application->review_notes = 'Auto-approved by system based on complete store profile and pricing criteria.';
            $application->save();

            $this->logAudit($store, 'store_auto_approved', $oldStatus, $store->status, array_merge([
                'auto_approved' => true,
                'system_approval' => true,
            ], $metadata));

            $this->notifyUser($store->owner, 'Toko Anda Otomatis Disetujui!', "Selamat! Toko '{$store->name}' telah otomatis disetujui dan aktif di marketplace.");

            return $store;
        });
    }

    /**
     * Check if a store meets all minimal requirements for conditional auto-approval.
     *
     * @return array{is_eligible: bool, missing_reasons: array<string>}
     */
    public function checkAutoApprovalEligibility(Store $store): array
    {
        $missing = [];

        // 1. Mandatory store profile fields
        $requiredFields = [
            'name' => 'Nama Toko',
            'phone' => 'Nomor Telepon Toko',
            'address' => 'Alamat Lengkap',
            'city' => 'Kota/Kabupaten',
            'province' => 'Provinsi',
            'postal_code' => 'Kode Pos',
        ];

        foreach ($requiredFields as $field => $label) {
            if (empty(trim((string) $store->{$field}))) {
                $missing[] = "Field {$label} belum diisi";
            }
        }

        // Phone format check (at least 8 digits)
        if (!empty($store->phone) && strlen(preg_replace('/[^0-9]/', '', $store->phone)) < 8) {
            $missing[] = 'Nomor telepon toko tidak valid (minimal 8 digit)';
        }

        // 2. Valid coordinates
        if ($store->latitude === null || $store->longitude === null || !is_numeric($store->latitude) || !is_numeric($store->longitude)) {
            $missing[] = 'Koordinat lokasi belum disetel';
        } elseif ((float) $store->latitude === 0.0 && (float) $store->longitude === 0.0) {
            $missing[] = 'Koordinat lokasi tidak boleh titik default (0, 0)';
        } elseif ($store->latitude < -90 || $store->latitude > 90 || $store->longitude < -180 || $store->longitude > 180) {
            $missing[] = 'Koordinat latitude/longitude di luar batas geografis valid';
        }

        // 3. Minimum 1 active service price > 0
        $hasActivePrice = $store->servicePrices()
            ->where('is_active', true)
            ->where('price_per_unit', '>', 0)
            ->exists();

        if (!$hasActivePrice) {
            $missing[] = 'Belum ada tarif layanan aktif dengan harga lebih dari 0';
        }

        // 4. Owner contact verification check (Zero-trust / Anti-abuse)
        $owner = $store->owner;
        if (! $owner || (! $owner->email_verified_at && ! $owner->phone_verified_at)) {
            $missing[] = 'Pemilik toko belum memverifikasi email atau nomor telepon';
        }

        return [
            'is_eligible' => empty($missing),
            'missing_reasons' => $missing,
        ];
    }

    /**
     * Reject store application.
     * Transitions: UNDER_REVIEW → REJECTED
     * Reason is required.
     */
    public function reject(Store $store, User $reviewer, string $reason): Store
    {
        Gate::forUser($reviewer)->authorize('approve', $store);

        if (empty($reason)) {
            throw new Exception('Rejection reason is required.');
        }

        return DB::transaction(function () use ($store, $reviewer, $reason) {
            $oldStatus = $store->status;

            if ($store->status !== StoreStatus::UNDER_REVIEW->value) {
                throw new Exception('Store must be UNDER_REVIEW to reject.');
            }

            $store->status = StoreStatus::REJECTED->value;
            $store->is_active = false;
            $store->save();

            $application = $store->application ?? new StoreApplication(['store_id' => $store->id]);
            $application->rejected_at = now();
            $application->rejection_reason = $reason;
            $application->save();

            $this->logAudit($store, 'store_rejected', $oldStatus, $store->status, [
                'reviewer_id' => $reviewer->id,
                'reason' => $reason,
            ]);

            $this->notifyUser($store->owner, 'Store rejected', "Your store '{$store->name}' was rejected. Reason: {$reason}");

            return $store;
        });
    }

    /**
     * Request revision.
     * Transitions: UNDER_REVIEW → REVISION_REQUIRED
     * Reason is required.
     */
    public function requestRevision(Store $store, User $reviewer, string $reason): Store
    {
        Gate::forUser($reviewer)->authorize('approve', $store);

        if (empty($reason)) {
            throw new Exception('Revision reason is required.');
        }

        return DB::transaction(function () use ($store, $reviewer, $reason) {
            $oldStatus = $store->status;

            if ($store->status !== StoreStatus::UNDER_REVIEW->value) {
                throw new Exception('Store must be UNDER_REVIEW to request revision.');
            }

            $store->status = StoreStatus::REVISION_REQUIRED->value;
            $store->save();

            $application = $store->application ?? new StoreApplication(['store_id' => $store->id]);
            $application->revision_required_at = now();
            $application->revision_notes = $reason;
            $application->save();

            $this->logAudit($store, 'store_revision_requested', $oldStatus, $store->status, [
                'reviewer_id' => $reviewer->id,
                'reason' => $reason,
            ]);

            $this->notifyUser($store->owner, 'Store revision required', "Your store '{$store->name}' needs revision. Reason: {$reason}");

            return $store;
        });
    }

    /**
     * Resubmit after revision.
     * Transitions: REVISION_REQUIRED → RESUBMITTED
     */
    public function resubmit(Store $store, array $data = []): Store
    {
        $this->validateSubmissionData($store, $data);

        return DB::transaction(function () use ($store) {
            $oldStatus = $store->status;

            if ($store->status !== StoreStatus::REVISION_REQUIRED->value) {
                throw new Exception('Store must be REVISION_REQUIRED to resubmit.');
            }

            // Update store fields if provided (allowed only in revision required)
            if (!empty($data)) {
                $store->update($data);
            }

            $store->status = StoreStatus::RESUBMITTED->value;
            $store->save();

            $application = $store->application;
            $application->resubmitted_at = now();
            $application->save();

            $this->logAudit($store, 'store_resubmitted', $oldStatus, $store->status, [
                'application_id' => $application->id,
            ]);

            $this->notifyUser($store->owner, 'Store resubmitted', "Your store '{$store->name}' has been resubmitted for review.");

            return $store;
        });
    }

    /**
     * Suspend an approved store.
     * Transitions: APPROVED → SUSPENDED
     * Reason is required.
     */
    public function suspend(Store $store, User $actor, string $reason): Store
    {
        Gate::forUser($actor)->authorize('approve', $store);

        if (empty($reason)) {
            throw new Exception('Suspension reason is required.');
        }

        return DB::transaction(function () use ($store, $actor, $reason) {
            $oldStatus = $store->status;

            if ($store->status !== StoreStatus::APPROVED->value) {
                throw new Exception('Only APPROVED stores can be suspended.');
            }

            $store->status = StoreStatus::SUSPENDED->value;
            $store->is_active = false;
            $store->suspended_at = now();
            $store->suspension_reason = $reason;
            $store->save();

            $this->logAudit($store, 'store_suspended', $oldStatus, $store->status, [
                'actor_id' => $actor->id,
                'reason' => $reason,
            ]);

            $this->notifyUser($store->owner, 'Store suspended', "Your store '{$store->name}' has been suspended. Reason: {$reason}");

            return $store;
        });
    }

    /**
     * Reactivate a suspended store (admin action).
     * Transitions: SUSPENDED → APPROVED
     */
    public function reactivate(Store $store, User $actor, ?string $note = null): Store
    {
        Gate::forUser($actor)->authorize('approve', $store);

        return DB::transaction(function () use ($store, $actor, $note) {
            $oldStatus = $store->status;

            if ($store->status !== StoreStatus::SUSPENDED->value) {
                throw new Exception('Only SUSPENDED stores can be reactivated.');
            }

            $store->status = StoreStatus::APPROVED->value;
            $store->is_active = true;
            $store->suspended_at = null;
            $store->suspension_reason = null;
            $store->save();

            $this->logAudit($store, 'store_reactivated', $oldStatus, $store->status, [
                'actor_id' => $actor->id,
                'note' => $note,
            ]);

            $this->notifyUser($store->owner, 'Store reactivated', "Your store '{$store->name}' has been reactivated.");

            return $store;
        });
    }

    /**
     * Validate submission data (required fields).
     */
    private function validateSubmissionData(Store $store, array $data): void
    {
        $required = [
            'name', 'slug', 'phone', 'address', 'city', 'province', 'postal_code',
            'latitude', 'longitude',
        ];

        $fields = array_merge($store->toArray(), $data);

        foreach ($required as $field) {
            if (empty($fields[$field])) {
                throw new Exception("Field '{$field}' is required for submission.");
            }
        }

        // Validate lat/lng
        if (!is_numeric($fields['latitude']) || !is_numeric($fields['longitude'])) {
            throw new Exception('Latitude and longitude must be numeric.');
        }
    }

    /**
     * Ensure required fields exist on the store model.
     */
    private function ensureRequiredFields(Store $store): void
    {
        $required = [
            'name', 'slug', 'phone', 'address', 'city', 'province', 'postal_code',
            'latitude', 'longitude',
        ];

        foreach ($required as $field) {
            if (empty($store->{$field})) {
                throw new Exception("Store missing required field '{$field}'. Please complete the store profile first.");
            }
        }
    }

    /**
     * Log audit entry.
     */
    private function logAudit(Store $store, string $action, string $oldStatus, string $newStatus, array $metadata = []): void
    {
        $actor = Auth::user();
        $actorId = $metadata['reviewer_id'] ?? $metadata['actor_id'] ?? $actor?->id ?? null;
        $actorRole = $actor?->roles()->first()?->name ?? ($actorId ? 'platform_admin' : 'system');

        AuditLog::create([
            'actor_id' => $actorId,
            'actor_role' => $actorRole,
            'action' => $action,
            'resource_type' => 'store',
            'resource_id' => $store->id,
            'before_value' => ['status' => $oldStatus],
            'after_value' => ['status' => $newStatus],
            'metadata' => array_merge([
                'admin_user_id' => $actorId,
            ], $metadata),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }

    /**
     * Send notification to user.
     */
    private function notifyUser(User $user, string $title, string $content): void
    {
        // Tahap 15: queued notification, never synchronous, never fails the transaction.
        NotificationTrigger::toUser($user, 'store_onboarding', $title, $content, ['store_onboarding' => true]);
    }

    /**
     * Check if store is eligible to receive orders (marketplace listing).
     */
    public function isEligibleForOrders(Store $store): bool
    {
        return $store->status === StoreStatus::APPROVED->value
            && $store->is_active
            && $store->servicePrices()->where('is_active', true)->exists()
            && $store->service_radius_km !== null;
    }

    /**
     * Get all eligible stores for marketplace listing.
     */
    public function getEligibleStores()
    {
        return Store::where('status', StoreStatus::APPROVED->value)
            ->where('is_active', true)
            ->whereHas('servicePrices', function ($q) {
                $q->where('is_active', true);
            })
            ->whereNotNull('service_radius_km')
            ->get();
    }
}