<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreLedgerEntry extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'store_id',
        'order_id',
        'entry_type',
        'direction',
        'amount',
        'reference_type',
        'reference_id',
        'description',
        'created_by',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
