<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'revision_required_at',
        'revision_notes',
        'resubmitted_at',
        'approved_at',
        'rejected_at',
        'rejection_reason',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'revision_required_at' => 'datetime',
        'resubmitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ========== RELATIONSHIPS ==========

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // ========== SCOPES ==========

    public function scopePending($query)
    {
        return $query->whereNull('reviewed_at')
            ->whereNull('rejected_at')
            ->whereNull('approved_at');
    }
}