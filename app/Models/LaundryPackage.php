<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LaundryPackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'unit',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}