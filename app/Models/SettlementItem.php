<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettlementItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'settlement_period_id',
        'order_id',
        'order_total',
        'commission_amount',
        'platform_fee_amount',
        'net_amount',
    ];

    protected $casts = [
        'order_total' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'platform_fee_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ========== RELATIONSHIPS ==========

    public function settlementPeriod(): BelongsTo
    {
        return $this->belongsTo(SettlementPeriod::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}