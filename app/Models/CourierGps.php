<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourierGps extends Model
{
    use HasFactory;

    protected $table = 'courier_gps';

    protected $fillable = [
        'task_id',
        'latitude',
        'longitude',
        'accuracy_meters',
        'heading',
        'speed_kmh',
        'recorded_at',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'accuracy_meters' => 'decimal:2',
        'heading' => 'decimal:2',
        'speed_kmh' => 'decimal:2',
        'recorded_at' => 'datetime',
    ];

    public $timestamps = false; // only recorded_at

    // ========== RELATIONSHIPS ==========

    public function task(): BelongsTo
    {
        return $this->belongsTo(CourierTask::class, 'task_id');
    }
}