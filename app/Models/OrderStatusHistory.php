<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusHistory extends Model
{
    use HasFactory;

    protected $table = 'order_status_history';

    protected $fillable = [
        'order_id',
        'status',
        'previous_status',
        'actor_id',
        'actor_role',
        'reason',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'json',
        'created_at' => 'datetime',
    ];

    const UPDATED_AT = null;

    // ========== RELATIONSHIPS ==========

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Record a new order status change safely with try-catch and logging.
     */
    public static function record(
        mixed $order,
        string $status,
        ?string $previousStatus = null,
        ?User $actor = null,
        ?string $reason = null,
        array $metadata = []
    ): ?self {
        $orderId = null;
        try {
            $orderId = $order instanceof Order ? $order->id : $order;
            $prev = $previousStatus ?? ($order instanceof Order ? ($order->getOriginal('status') ?? 'created') : 'created');
            $actorRole = $actor?->role ?? $actor?->roles()->first()?->name ?? null;

            return static::create([
                'order_id' => $orderId,
                'status' => $status,
                'previous_status' => $prev,
                'actor_id' => $actor?->id,
                'actor_role' => $actorRole,
                'reason' => $reason,
                'metadata' => $metadata,
            ]);
        } catch (\Throwable $e) {
            $safeOrderId = is_scalar($orderId) ? (string) $orderId : json_encode($orderId);
            \Illuminate\Support\Facades\Log::warning("Failed to record order status history for order {$safeOrderId}: " . $e->getMessage(), [
                'exception' => $e,
                'order_id' => $orderId,
                'status' => $status,
            ]);
            return null;
        }
    }
}