<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformLedgerEntry extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'store_id',
        'order_id',
        'entry_type',
        'amount',
        'status',
    ];

    protected $casts = [
        'amount' => 'integer',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
