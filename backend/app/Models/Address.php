<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'addresses';

    protected $fillable = [
        'user_id',
        'label',
        'address_text',
        'latitude',
        'longitude',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
