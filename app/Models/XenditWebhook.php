<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class XenditWebhook extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_type',
        'payload',
        'signature',
        'processed',
        'processed_at',
        'error',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed' => 'boolean',
        'processed_at' => 'datetime',
    ];
}