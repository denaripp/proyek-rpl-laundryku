<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerAddress extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'label',
        'recipient_name',
        'phone',
        'address',
        'rt_rw',
        'kelurahan',
        'kecamatan',
        'city',
        'province',
        'postal_code',
        'latitude',
        'longitude',
        'address_note',
        'is_default',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'is_default' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}