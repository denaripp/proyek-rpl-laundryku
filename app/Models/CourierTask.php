<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourierTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'courier_id',
        'task_type',
        'status',
        'assigned_at',
        'accepted_at',
        'arrived_at',
        'completed_at',
        'failed_at',
        'failure_reason',
        'cancelled_at',
        'cancellation_reason',
        'distance_km',
        'duration_minutes',
    ];

    protected $casts = [
        'task_type' => 'string',
        'status' => 'string',
        'distance_km' => 'decimal:2',
        'duration_minutes' => 'integer',
        'assigned_at' => 'datetime',
        'accepted_at' => 'datetime',
        'arrived_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ========== RELATIONSHIPS ==========

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(Courier::class);
    }

    public function gpsLogs(): HasMany
    {
        return $this->hasMany(CourierGps::class);
    }

    // ========== SCOPES ==========

    public function scopePending($query)
    {
        return $query->where('status', 'assigned');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['accepted', 'arrived', 'picked_up']);
    }
}