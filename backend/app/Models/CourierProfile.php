<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CourierProfile extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'courier_profiles';

    protected $fillable = [
        'user_id',
        'store_id',
        'identity_encrypted',
        'ktp_storage_key',
        'photo_storage_key',
        'vehicle_type',
        'vehicle_plate_encrypted',
        'verification_status',
        'rejection_reason',
        'status',
    ];

    /**
     * Use Laravel's encrypted cast for NIK/identity and vehicle plate.
     */
    protected function casts(): array
    {
        return [
            'identity_encrypted' => 'encrypted',
            'vehicle_plate_encrypted' => 'encrypted',
        ];
    }

    /**
     * Sensitive fields hidden by default from any JSON serialization.
     */
    protected $hidden = [
        'identity_encrypted',
        'vehicle_plate_encrypted',
        'ktp_storage_key',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function storeAssignments(): HasMany
    {
        return $this->hasMany(CourierStoreAssignment::class, 'courier_id');
    }

    public function activeAssignment(): HasOne
    {
        return $this->hasOne(CourierStoreAssignment::class, 'courier_id')->where('status', 'active');
    }
}
