<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Courier extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'store_id',
        'vehicle_type',
        'vehicle_plate',
        'is_verified',
        'is_available',
        'current_latitude',
        'current_longitude',
        'last_location_update',
        'service_area',
        'rating',
        'total_tasks',
        'completed_tasks',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'is_available' => 'boolean',
        'current_latitude' => 'decimal:8',
        'current_longitude' => 'decimal:8',
        'service_area' => 'json',
        'rating' => 'decimal:2',
        'total_tasks' => 'integer',
        'completed_tasks' => 'integer',
        'last_location_update' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ========== RELATIONSHIPS ==========

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(CourierTask::class);
    }

    public function gpsLogs(): HasMany
    {
        return $this->hasMany(CourierGps::class, 'task_id'); // Actually via tasks, but we'll use tasks relation
    }

    // ========== SCOPES ==========

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true)
            ->where('is_verified', true);
    }

    public function scopeNearby($query, $lat, $lng, $radiusKm = 10)
    {
        // Simplified: use raw or implement haversine
        return $query->whereRaw(
            "ST_Distance_Sphere(point(current_longitude, current_latitude), point(?, ?)) <= ?",
            [$lng, $lat, $radiusKm * 1000]
        );
    }
}