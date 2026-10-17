<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicePrice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'store_id',
        'service_id',
        'price_per_unit',
        'unit',
        'is_active',
    ];

    protected $casts = [
        'price_per_unit' => 'decimal:2',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ========== RELATIONSHIPS ==========

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    // ========== SCOPES ==========

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}