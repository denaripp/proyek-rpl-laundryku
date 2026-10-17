<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'event_type',
        'xendit_event_id',
        'payload',
        'occurred_at',
    ];

    protected $casts = [
        'payload' => 'json',
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}