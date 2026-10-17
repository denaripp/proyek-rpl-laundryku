<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentWebhookEvent extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'provider',
        'provider_event_id',
        'idempotency_key',
        'payload_hash',
        'processing_status',
        'raw_payload',
        'received_at',
        'processed_at',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];
}
