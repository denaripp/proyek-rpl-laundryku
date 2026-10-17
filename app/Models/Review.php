<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'customer_id',
        'store_id',
        'rating',
        'comment',
        'is_visible',
        'moderated_at',
        'moderated_by',
        'moderation_reason',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_visible' => 'boolean',
        'moderated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted()
    {
        // Keep the store's precomputed rating aggregate in sync on any change.
        static::created(fn (Review $review) => static::recomputeStoreAggregate($review->store_id));
        static::updated(fn (Review $review) => static::recomputeStoreAggregate($review->store_id));
        static::deleted(fn (Review $review) => static::recomputeStoreAggregate($review->store_id));
    }

    /**
     * Recompute average rating + review count for a store from visible reviews.
     */
    public static function recomputeStoreAggregate(int $storeId): void
    {
        $stats = static::where('store_id', $storeId)
            ->where('is_visible', true)
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as cnt')
            ->first();

        Store::where('id', $storeId)->update([
            'average_rating' => $stats && $stats->cnt > 0 ? round((float) $stats->avg_rating, 2) : null,
            'review_count' => $stats ? (int) $stats->cnt : 0,
        ]);
    }

    // ========== RELATIONSHIPS ==========

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function moderatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    // ========== SCOPES ==========

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    public function scopeHighRating($query, $min = 4)
    {
        return $query->where('rating', '>=', $min);
    }
}