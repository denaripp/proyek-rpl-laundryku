<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'customer_addresses';

    protected $fillable = [
        'user_id',
        'label',
        'recipient_name',
        'phone',
        'address',
        'rt_rw',
        'kelurahan',
        'kecamatan',
        'city',
        'province',
        'postal_code',
        'latitude',
        'longitude',
        'address_note',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $appends = [
        'address_text',
    ];

    public function getAddressTextAttribute(): ?string
    {
        return $this->address;
    }


    // ========== RELATIONSHIPS ==========

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Orders where this address is used as pickup or delivery
    public function pickupOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'pickup_address_id');
    }

    public function deliveryOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'delivery_address_id');
    }

    // ========== SCOPES ==========

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}