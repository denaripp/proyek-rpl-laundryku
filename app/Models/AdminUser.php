<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class AdminUser extends User
{
    protected $table = 'users';

    protected static function booted(): void
    {
        static::addGlobalScope('role_super_admin', function (Builder $builder) {
            $builder->where('role', self::ROLE_SUPER_ADMIN);
        });

        static::creating(function (User $user) {
            $user->role = self::ROLE_SUPER_ADMIN;
        });
    }
}
