<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'code',
        'name',
    ];

    /**
     * Users that have this role.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles', 'role_id', 'user_id')
            ->withPivot('store_id', 'status')
            ->withTimestamps();
    }

    /**
     * User roles pivot records.
     */
    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class, 'role_id');
    }
}
