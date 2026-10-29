<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Store extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'owner_user_id',
        'name',
        'slug',
        'logo',
        'description',
        'phone',
        'whatsapp',
        'address',
        'rt_rw',
        'kelurahan',
        'kecamatan',
        'city',
        'province',
        'postal_code',
        'latitude',
        'longitude',
        'is_active',
        'status',
        'cod_enabled',
        'qris_enabled',
        'va_enabled',
        'va_banks',
        'operational_hours',
        'service_radius_km',
        'minimum_order_amount',
        'pickup_fee',
        'delivery_fee',
        'commission_rate',
        'average_rating',
        'review_count',
        'approved_at',
        'suspended_at',
        'suspension_reason',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'cod_enabled' => 'boolean',
        'qris_enabled' => 'boolean',
        'va_enabled' => 'boolean',
        'va_banks' => 'array',
        'operational_hours' => 'json',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'service_radius_km' => 'decimal:2',
        'minimum_order_amount' => 'decimal:2',
        'pickup_fee' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'average_rating' => 'decimal:2',
        'review_count' => 'integer',
        'approved_at' => 'datetime',
        'suspended_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ========== RELATIONSHIPS ==========

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function staff(): HasMany
    {
        return $this->hasMany(StoreStaff::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function application(): HasOne
    {
        return $this->hasOne(StoreApplication::class);
    }

    public function servicePrices(): HasMany
    {
        return $this->hasMany(ServicePrice::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(StoreSetting::class);
    }

    public function couriers(): HasMany
    {
        return $this->hasMany(Courier::class);
    }

    public function payoutAccount(): HasOne
    {
        return $this->hasOne(StorePayoutAccount::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    // ========== SCOPES ==========

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved')
            ->where('is_active', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    // ========== HELPERS ==========

    public function isApproved(): bool
    {
        return $this->status === 'approved' && $this->is_active;
    }

    // ========== SCOPES FOR SEARCH ==========

    public function scopeWithinBounds($query, float $lat, float $lng, float $margin)
    {
        return $query->whereBetween('latitude', [$lat - $margin, $lat + $margin])
                     ->whereBetween('longitude', [$lng - $margin, $lng + $margin]);
    }

    public function scopeWithinRadius($query, float $lat, float $lng, float $radius)
    {
        // SECURITY (Tahap 19): gunakan parameter binding untuk lat/lng
        // meskipun caller sudah divalidasi numeric di FormRequest, defense in depth.
        $distanceSql = "6371 * ACOS(
            COS(RADIANS(?)) * COS(RADIANS(latitude)) *
            COS(RADIANS(longitude) - RADIANS(?)) +
            SIN(RADIANS(?)) * SIN(RADIANS(latitude))
        )";
        return $query->select('stores.*')
                     ->selectRaw("{$distanceSql} as distance", [$lat, $lng, $lat])
                     ->havingRaw("distance <= ?", [$radius]);
    }
}