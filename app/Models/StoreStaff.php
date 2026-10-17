<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreStaff extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'store_id',
        'user_id',
        'permissions',
        'is_active',
        'invited_at',
        'accepted_at',
    ];

    protected $casts = [
        'permissions' => 'json',
        'is_active' => 'boolean',
        'invited_at' => 'datetime',
        'accepted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ========== RELATIONSHIPS ==========

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ========== SCOPES ==========

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}