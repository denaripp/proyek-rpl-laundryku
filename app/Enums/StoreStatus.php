<?php

namespace App\Enums;

enum StoreStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case UNDER_REVIEW = 'under_review';
    case REVISION_REQUIRED = 'revision_required';
    case RESUBMITTED = 'resubmitted';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case SUSPENDED = 'suspended';

    public function isDraft(): bool
    {
        return $this === self::DRAFT;
    }

    public function isSubmitted(): bool
    {
        return in_array($this, [self::SUBMITTED, self::UNDER_REVIEW, self::REVISION_REQUIRED, self::RESUBMITTED]);
    }

    public function isUnderReview(): bool
    {
        return $this === self::UNDER_REVIEW;
    }

    public function isRevisionRequired(): bool
    {
        return $this === self::REVISION_REQUIRED;
    }

    public function isResubmitted(): bool
    {
        return $this === self::RESUBMITTED;
    }

    public function isApproved(): bool
    {
        return $this === self::APPROVED;
    }

    public function isRejected(): bool
    {
        return $this === self::REJECTED;
    }

    public function isSuspended(): bool
    {
        return $this === self::SUSPENDED;
    }

    public function isActive(): bool
    {
        return $this === self::APPROVED;
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::DRAFT, self::REVISION_REQUIRED, self::REJECTED]);
    }

    public function canSubmit(): bool
    {
        return $this === self::DRAFT || $this === self::REVISION_REQUIRED || $this === self::REJECTED;
    }

    public function canResubmit(): bool
    {
        return $this === self::REVISION_REQUIRED;
    }

    public function isMarketplaceEligible(): bool
    {
        return $this === self::APPROVED;
    }
}