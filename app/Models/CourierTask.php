<?php

namespace App\Models;

use App\Enums\CourierTaskStatus;
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
        return $query->where('status', CourierTaskStatus::ASSIGNED->value);
    }

    public const ACTIVE_STATUSES = [
        CourierTaskStatus::ASSIGNED->value,
        CourierTaskStatus::ACCEPTED->value,
        CourierTaskStatus::ARRIVED->value,
        CourierTaskStatus::PICKED_UP->value,
    ];

    /**
     * Close active courier tasks for a given order and task type.
     */
    public static function closeActiveFor(Order $order, string $type): int
    {
        return static::where('order_id', $order->id)
            ->where('task_type', $type)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->update([
                'status' => CourierTaskStatus::DELIVERED->value,
                'completed_at' => now(),
            ]);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            CourierTaskStatus::ACCEPTED->value,
            CourierTaskStatus::ARRIVED->value,
            CourierTaskStatus::PICKED_UP->value,
        ]);
    }
}