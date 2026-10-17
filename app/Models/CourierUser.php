<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class CourierUser extends User
{
    protected $table = 'users';

    protected static function booted(): void
    {
        static::addGlobalScope('role_courier', function (Builder $builder) {
            $builder->where('role', self::ROLE_COURIER);
        });

        static::creating(function (User $user) {
            $user->role = self::ROLE_COURIER;
        });
    }
}
