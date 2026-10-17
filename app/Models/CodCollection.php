<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CodCollection extends Model
{
    protected $fillable = [
        'order_id',
        'courier_id',
        'store_id',
        'amount',
        'status',
        'otp_code',
        'otp_expires_at',
        'verified_at',
        'deposited_at',
        'reconciled_at',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'otp_expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'deposited_at' => 'datetime',
        'reconciled_at' => 'datetime',
    ];

    // Status Constants
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_COLLECTED = 'COLLECTED';
    public const STATUS_DEPOSITED = 'DEPOSITED';
    public const STATUS_RECONCILED = 'RECONCILED';

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(Courier::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function isCollected(): bool
    {
        return in_array($this->status, [self::STATUS_COLLECTED, self::STATUS_DEPOSITED, self::STATUS_RECONCILED]);
    }

    public function isReconciled(): bool
    {
        return $this->status === self::STATUS_RECONCILED;
    }
}
