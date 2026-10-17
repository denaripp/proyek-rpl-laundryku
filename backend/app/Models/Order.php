<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'orders';

    protected $fillable = [
        'order_number',
        'customer_id',
        'store_id',
        'courier_id',
        'pickup_address_snapshot',
        'delivery_address_snapshot',
        'status',
        'payment_status',
        'estimated_weight_kg',
        'actual_weight_kg',
        'estimated_amount',
        'final_amount',
        'delivery_fee',
        'commission_amount',
        'courier_wage',
        'customer_notes',
        'price_approved_at',
        'cancelled_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'pickup_address_snapshot' => 'array',
            'delivery_address_snapshot' => 'array',
            'estimated_weight_kg' => 'float',
            'actual_weight_kg' => 'float',
            'estimated_amount' => 'integer',
            'final_amount' => 'integer',
            'delivery_fee' => 'integer',
            'commission_amount' => 'integer',
            'courier_wage' => 'integer',
            'price_approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class, 'order_id')->orderBy('created_at', 'asc');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(OrderAssignment::class, 'order_id')->orderBy('assigned_at', 'desc');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'order_id')->orderBy('created_at', 'desc');
    }

    public function storeLedgerEntries(): HasMany
    {
        return $this->hasMany(StoreLedgerEntry::class, 'order_id')->orderBy('created_at', 'asc');
    }

    public function platformLedgerEntries(): HasMany
    {
        return $this->hasMany(PlatformLedgerEntry::class, 'order_id')->orderBy('created_at', 'asc');
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class, 'order_id')->orderBy('created_at', 'desc');
    }

    public function rating(): HasOne
    {
        return $this->hasOne(Rating::class, 'order_id');
    }
}

