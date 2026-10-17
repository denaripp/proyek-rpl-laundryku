<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SettlementPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'store_id',
        'period_start',
        'period_end',
        'interval',
        'gross_amount',
        'commission_amount',
        'platform_fee_amount',
        'net_amount',
        'order_count',
        'status',
        'verified_by',
        'verified_at',
        'config_snapshot',
    ];

    protected $casts = [
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'gross_amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'platform_fee_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'order_count' => 'integer',
        'verified_at' => 'datetime',
        'config_snapshot' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ========== RELATIONSHIPS ==========

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SettlementItem::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    // ========== SCOPES ==========

    public function scopeForStore($query, $storeId)
    {
        return $query->where('store_id', $storeId);
    }
}