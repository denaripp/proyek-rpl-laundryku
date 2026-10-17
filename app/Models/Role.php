<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    // ========== RELATIONSHIPS ==========

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles')
            ->using(UserRole::class)
            ->withPivot('store_id', 'assigned_at', 'assigned_by')
            ->withTimestamps();
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    // ========== SCOPES ==========

    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }
}