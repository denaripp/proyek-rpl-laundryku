<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class CustomerUser extends User
{
    protected $table = 'users';

    protected static function booted(): void
    {
        static::addGlobalScope('role_customer', function (Builder $builder) {
            $builder->where('role', self::ROLE_CUSTOMER);
        });

        static::creating(function (User $user) {
            $user->role = self::ROLE_CUSTOMER;
        });
    }
}
