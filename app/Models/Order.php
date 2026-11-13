<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_number',
        'customer_id',
        'store_id',
        'status',
        'pickup_address_id',
        'delivery_address_id',
        'pickup_address',
        'delivery_address',
        'pickup_latitude',
        'pickup_longitude',
        'delivery_latitude',
        'delivery_longitude',
        'pickup_address_detail',
        'delivery_address_detail',
        'weight',
        'total_price',
        'order_date',
        'estimated_weight',
        'actual_weight',
        'weight_unit',
        'estimated_price',
        'final_price',
        'pickup_fee',
        'delivery_fee',
        'platform_fee',
        'discount',
        'tax',
        'total_amount',
        'payment_method',
        'payment_status',
        'courier_id',
        'courier_assigned_at',
        'courier_arrived_at',
        'picked_up_at',
        'received_by_store_at',
        'weighed_at',
        'paid_at',
        'processing_started_at',
        'ready_at',
        'delivered_at',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
        'cancelled_by',
        'customer_notes',
        'store_notes',
    ];

    protected $casts = [
        'status' => 'string',
        'payment_status' => 'string',
        'estimated_weight' => 'decimal:2',
        'actual_weight' => 'decimal:2',
        'estimated_price' => 'decimal:2',
        'final_price' => 'decimal:2',
        'pickup_fee' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'pickup_latitude' => 'decimal:8',
        'pickup_longitude' => 'decimal:8',
        'delivery_latitude' => 'decimal:8',
        'delivery_longitude' => 'decimal:8',
        'pickup_address_detail' => 'array',
        'delivery_address_detail' => 'array',
        'courier_assigned_at' => 'datetime',
        'courier_arrived_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'received_by_store_at' => 'datetime',
        'weighed_at' => 'datetime',
        'paid_at' => 'datetime',
        'processing_started_at' => 'datetime',
        'ready_at' => 'datetime',
        'delivered_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ========== RELATIONSHIPS ==========

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_id');
    }

    public function pickupAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'pickup_address_id');
    }

    public function deliveryAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'delivery_address_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function refund(): HasOne
    {
        return $this->hasOne(Refund::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function settlementItem(): HasOne
    {
        return $this->hasOne(SettlementItem::class);
    }

    public function courierTasks(): HasMany
    {
        return $this->hasMany(CourierTask::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class);
    }

    // ========== SCOPES ==========

    public function scopeActiveForCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId)
            ->whereNotIn('status', ['completed', 'cancelled', 'delivery_failed', 'pickup_failed']);
    }

    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopePendingPayment($query)
    {
        return $query->where('payment_status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    // ========== HELPERS ==========

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isWeighed(): bool
    {
        return $this->actual_weight !== null;
    }

    /**
     * Calculate financial breakdown (service fee, platform commission, store net income).
     * Single source of truth for order financial breakdown.
     */
    public function calculateFinancialBreakdown(): array
    {
        $isWeighed = $this->isWeighed();

        // 1. service_fee calculation
        // Prioritas (1) jumlah items.line_final_amount kalau semua terisi
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();
        $hasItems = $items->isNotEmpty();
        $allLineFinalFilled = $hasItems && $items->every(fn ($it) => $it->line_final_amount !== null && is_numeric($it->line_final_amount));

        if ($allLineFinalFilled) {
            $serviceFee = (float) $items->sum('line_final_amount');
        } elseif ($isWeighed && $this->final_price !== null) {
            // Prioritas (2) kalau sudah ditimbang: final_price - platform_fee - delivery_fee - pickup_fee + discount
            $serviceFee = (float) $this->final_price - (float) ($this->platform_fee ?? 0) - (float) ($this->delivery_fee ?? 0) - (float) ($this->pickup_fee ?? 0) + (float) ($this->discount ?? 0);
        } else {
            // Prioritas (3) kalau belum ditimbang: jumlah line_estimated_amount
            if ($hasItems) {
                $serviceFee = (float) $items->sum(function ($it) {
                    $unitPrice = (float) ($it->unit_price ?? $it->price_per_unit ?? 0);
                    $qty = (float) ($it->quantity_estimated ?? $it->quantity ?? 0);
                    $calculatedSubtotal = $unitPrice * $qty;
                    if ($it->minimum_charge && $calculatedSubtotal < (float) $it->minimum_charge) {
                        $calculatedSubtotal = (float) $it->minimum_charge;
                    }
                    return (float) ($it->line_estimated_amount ?? ($it->total_price ?? ($it->subtotal ?? $calculatedSubtotal)));
                });
            } else {
                $serviceFee = max(0, (float) ($this->estimated_price ?? $this->total_amount ?? 0) - (float) ($this->platform_fee ?? 0) - (float) ($this->delivery_fee ?? 0) - (float) ($this->pickup_fee ?? 0) + (float) ($this->discount ?? 0));
            }
        }

        $serviceFee = max(0, (float) $serviceFee);

        // 2. Commission calculation
        $store = $this->relationLoaded('store') ? $this->store : $this->store()->first();
        $commissionRate = $store?->commission_rate;
        $commissionConfigured = $commissionRate !== null;
        $rateNumeric = $commissionConfigured ? (float) $commissionRate : 0.0;
        $commissionAmount = $commissionConfigured ? (float) round(($rateNumeric / 100) * $serviceFee, 2) : 0.0;

        // 3. store_net_income: service_fee - commission_amount
        $storeNetIncome = max(0, (float) round($serviceFee - $commissionAmount, 2));

        return [
            'service_fee' => $serviceFee,
            'is_weighed' => $isWeighed,
            'commission_rate' => $rateNumeric,
            'commission_amount' => $commissionAmount,
            'commission_configured' => $commissionConfigured,
            'store_net_income' => $storeNetIncome,
        ];
    }
}
