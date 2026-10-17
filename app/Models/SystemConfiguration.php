<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'description',
        'is_editable',
    ];

    protected $casts = [
        'value' => 'array',
        'is_editable' => 'boolean',
    ];
}