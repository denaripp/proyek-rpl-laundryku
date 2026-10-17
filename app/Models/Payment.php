<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'payment_method',
        'amount',
        'status',
        'xendit_invoice_id',
        'xendit_payment_id',
        'xendit_payment_method',
        'xendit_payment_channel',
        'payment_reference',
        'expires_at',
        'paid_at',
        'customer_ip',
        'customer_user_agent',
        'metadata',
        'idempotency_key',
        'webhook_received_at',
        'xendit_webhook_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'status' => 'string',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'metadata' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ========== RELATIONSHIPS ==========

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function refund(): HasOne
    {
        return $this->hasOne(Refund::class);
    }

    // ========== SCOPES ==========

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }
}