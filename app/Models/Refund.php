<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'payment_id',
        'amount',
        'status',
        'reason',
        'method',
        'refund_reference',
        'xendit_refund_id',
        'requested_by',
        'approved_by',
        'approved_at',
        'processed_at',
        'completed_at',
        'failed_at',
        'failure_reason',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'status' => 'string',
        'approved_at' => 'datetime',
        'processed_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'metadata' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ========== RELATIONSHIPS ==========

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ========== SCOPES ==========

    public function scopePending($query)
    {
        return $query->where('status', 'requested');
    }
}