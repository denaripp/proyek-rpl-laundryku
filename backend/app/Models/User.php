<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\CourierStoreAssignment;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'password',
        'status',
        'status_reason',
        'verified_at',
        'active_mode',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id')
            ->withPivot('store_id', 'status')
            ->withTimestamps();
    }

    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class, 'user_id');
    }

    public function otpChallenges(): HasMany
    {
        return $this->hasMany(OtpChallenge::class, 'user_id');
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class, 'user_id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class, 'user_id');
    }

    public function defaultAddress(): HasOne
    {
        return $this->hasOne(Address::class, 'user_id')->where('is_default', true);
    }

    public function courierProfile(): HasOne
    {
        return $this->hasOne(CourierProfile::class, 'user_id');
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class, 'owner_user_id');
    }

    public function activeRoles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id')
            ->wherePivot('status', 'active')
            ->withPivot('store_id', 'status')
            ->withTimestamps();
    }

    public function hasRole(string $code): bool
    {
        if ($this->relationLoaded('roles')) {
            return $this->roles
                ->filter(fn ($role) => ($role->pivot->status ?? null) === 'active')
                ->contains('code', $code);
        }

        return $this->roles()
            ->wherePivot('status', 'active')
            ->where('code', $code)
            ->exists();
    }

    public function isVerified(): bool
    {
        return $this->status === 'active' && !is_null($this->verified_at);
    }

    public function complaints(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Complaint::class, 'customer_id')->orderBy('created_at', 'desc');
    }

    public function ratings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Rating::class, 'customer_id')->orderBy('created_at', 'desc');
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->profile?->avatar_url;
    }

    public function hasActiveCourierAssignment(): bool
    {
        return CourierStoreAssignment::whereHas('courier', fn ($q) => $q->where('user_id', $this->id))
            ->where('status', 'active')
            ->exists();
    }

    public function hasApprovedStore(): bool
    {
        return $this->stores()->where('approval_status', 'approved')->exists();
    }

    public function getAvailableModes(): array
    {
        $modes = ['customer'];
        if ($this->hasApprovedStore() || $this->hasRole('store_admin')) {
            $modes[] = 'store';
        }
        if ($this->hasActiveCourierAssignment()) {
            $modes[] = 'courier';
        }
        return array_values(array_unique($modes));
    }
}

