<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_STORE_ADMIN = 'store_admin';
    public const ROLE_COURIER = 'courier';
    public const ROLE_CUSTOMER = 'customer';

    protected $fillable = [
        'name',
        'email',
        'role',
        'phone',
        'password',
        'google_id',
        'profile_photo',
        'is_active',
        'google2fa_secret',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'google2fa_secret' => 'string',
    ];

    protected $appends = [
        'status',
        'has_real_email',
        'display_email',
        'display_phone',
    ];

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isStoreAdmin(): bool
    {
        return $this->role === self::ROLE_STORE_ADMIN;
    }

    public function isCourier(): bool
    {
        return $this->role === self::ROLE_COURIER;
    }

    public function isCustomer(): bool
    {
        return $this->role === self::ROLE_CUSTOMER;
    }

    // ========== RELATIONSHIPS ==========

    // Roles via user_roles (many-to-many with extra fields)
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id')
            ->using(UserRole::class)
            ->withPivot('store_id', 'assigned_at', 'assigned_by')
            ->withTimestamps();
    }

    // Direct permissions via roles (not direct, but we can use hasManyThrough or accessor)

    // Store ownership
    public function ownedStores(): HasMany
    {
        return $this->hasMany(Store::class, 'owner_user_id');
    }

    // Staff assignments
    public function storeStaff(): HasMany
    {
        return $this->hasMany(StoreStaff::class, 'user_id');
    }

    // Orders as customer
    public function ordersAsCustomer(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    // Orders as courier (through couriers table)
    public function courierOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'courier_id');
    }

    // Courier profile
    public function courier(): HasOne
    {
        return $this->hasOne(Courier::class, 'user_id');
    }

    // Addresses
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class, 'user_id');
    }

    // Notifications
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'user_id');
    }

    // Reviews written
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'customer_id');
    }

    // Audit logs as actor
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_id');
    }

    // AI conversations
    public function aiConversations(): HasMany
    {
        return $this->hasMany(AiConversation::class, 'user_id');
    }

    public function otpChallenges(): HasMany
    {
        return $this->hasMany(OtpChallenge::class, 'user_id');
    }

    public function getStatusAttribute(): string
    {
        if (! $this->is_active) {
            return 'inactive';
        }
        if (is_null($this->email_verified_at) && is_null($this->phone_verified_at)) {
            return 'pending_verification';
        }
        return 'active';
    }

    /**
     * Determine if an email address is an auto-generated WhatsApp placeholder.
     * Matches only pattern: ^wa_\d+@laundryku\.test$
     */
    public static function isPlaceholderEmail(?string $email): bool
    {
        if (empty($email)) {
            return false;
        }

        $email = strtolower(trim($email));

        return (bool) preg_match('/^wa_\d+@laundryku\.test$/i', $email);
    }

    /**
     * Check if user has a verified/real human email (not placeholder).
     */
    public function getHasRealEmailAttribute(): bool
    {
        return !empty($this->email) && !self::isPlaceholderEmail($this->email);
    }

    /**
     * Email suitable for UI display (null if placeholder).
     */
    public function getDisplayEmailAttribute(): ?string
    {
        return $this->has_real_email ? $this->email : null;
    }

    /**
     * Phone formatted internationally (+62...) for UI display.
     */
    public function getDisplayPhoneAttribute(): ?string
    {
        if (empty($this->phone)) {
            return null;
        }

        $cleaned = preg_replace('/[^0-9]/', '', (string) $this->phone);
        if (str_starts_with($cleaned, '0')) {
            $cleaned = '62' . substr($cleaned, 1);
        } elseif (str_starts_with($cleaned, '8')) {
            $cleaned = '62' . $cleaned;
        }

        return '+' . $cleaned;
    }


    // ========== SCOPES ==========

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeWithRole($query, $roleName)
    {
        return $query->whereHas('roles', function ($q) use ($roleName) {
            $q->where('name', $roleName);
        });
    }

    // ========== PERMISSION HELPERS ==========

    public function hasPermissionTo($permissionName)
    {
        return $this->roles()->whereHas('permissions', function ($q) use ($permissionName) {
            $q->where('name', $permissionName);
        })->exists();
    }

    public function hasRole(string $roleName): bool
    {
        return $this->roles()->where('name', $roleName)->exists();
    }
}