<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceCategory extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'service_categories';

    protected $fillable = [
        'name',
        'slug',
        'status',
    ];

    public function services(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StoreService::class, 'category_id');
    }
}
