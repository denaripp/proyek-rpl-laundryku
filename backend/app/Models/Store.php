<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'stores';

    protected $fillable = [
        'owner_user_id',
        'name',
        'description',
        'address_text',
        'latitude',
        'longitude',
        'service_radius_km',
        'opening_hours',
        'approval_status',
        'operational_status',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];


    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'service_radius_km' => 'float',
            'opening_hours' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(StoreDocument::class, 'store_id');
    }

    public function courierAssignments(): HasMany
    {
        return $this->hasMany(CourierStoreAssignment::class, 'store_id');
    }

    public function services(): HasMany
    {
        return $this->hasMany(StoreService::class, 'store_id');
    }

    public function activeServices(): HasMany
    {
        return $this->hasMany(StoreService::class, 'store_id')->where('is_active', true);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(StoreLedgerEntry::class, 'store_id')->orderBy('created_at', 'desc');
    }

    public function platformLedgerEntries(): HasMany
    {
        return $this->hasMany(PlatformLedgerEntry::class, 'store_id')->orderBy('created_at', 'desc');
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class, 'store_id')->orderBy('created_at', 'desc');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class, 'store_id')->orderBy('created_at', 'desc');
    }
}

