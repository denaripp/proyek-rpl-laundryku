<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'commission_rate',
        'fee_type',
        'fixed_amount',
        'effective_from',
        'effective_until',
        'is_active',
    ];

    protected $casts = [
        'commission_rate' => 'decimal:2',
        'fixed_amount' => 'decimal:2',
        'effective_from' => 'date',
        'effective_until' => 'date',
        'is_active' => 'boolean',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}