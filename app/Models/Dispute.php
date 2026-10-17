<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dispute extends Model
{
    use HasFactory;

    const STATUS_COMPLAINT_SUBMITTED = 'complaint_submitted';
    const STATUS_UNDER_REVIEW = 'under_review';
    const STATUS_INVESTIGATION = 'investigation';
    const STATUS_RESOLVED = 'resolved';
    const STATUS_REJECTED = 'rejected';

    const OPEN_STATUSES = [
        self::STATUS_COMPLAINT_SUBMITTED,
        self::STATUS_UNDER_REVIEW,
        self::STATUS_INVESTIGATION,
    ];

    protected $fillable = [
        'complaint_number',
        'order_id',
        'opened_by',
        'reason',
        'description',
        'status',
        'evidence',
        'assigned_to',
        'resolution_outcome',
        'resolution_note',
        'resolved_by',
        'resolved_at',
        'refund_id',
        'sla_due_at',
        'metadata',
    ];

    protected $casts = [
        'evidence' => 'json',
        'metadata' => 'json',
        'resolved_at' => 'datetime',
        'sla_due_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ========== RELATIONSHIPS ==========

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(DisputeEvent::class);
    }

    // ========== SCOPES ==========

    public function scopeOpen($query)
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    // ========== HELPERS ==========

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES);
    }

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }
}