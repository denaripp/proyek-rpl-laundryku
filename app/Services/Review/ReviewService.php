<?php

namespace App\Services\Review;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Review;
use App\Models\Store;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;

class ReviewService
{
    /**
     * Create a review for a completed order (1 review per order).
     * Recomputes the store's precomputed rating aggregate.
     */
    public function createReview(Order $order, User $customer, int $rating, ?string $comment): Review
    {
        if ($order->status !== 'completed') {
            throw new Exception('Review can only be created for COMPLETED orders.');
        }

        if ($order->customer_id !== $customer->id) {
            throw new Exception('You can only review your own order.');
        }

        if ($order->review()->exists()) {
            throw new Exception('This order has already been reviewed.');
        }

        if ($rating < 1 || $rating > 5) {
            throw new Exception('Rating must be between 1 and 5.');
        }

        return DB::transaction(function () use ($order, $customer, $rating, $comment) {
            $review = Review::create([
                'order_id' => $order->id,
                'customer_id' => $customer->id,
                'store_id' => $order->store_id,
                'rating' => $rating,
                'comment' => $comment,
                'is_visible' => true,
            ]);

            // Recompute precomputed aggregate (no per-search aggregate query needed)
            $this->recomputeStoreRating($order->store_id);

            $this->logAudit($customer, 'review_created', 'reviews', $review->id, null, [
                'order_id' => $order->id,
                'store_id' => $order->store_id,
                'rating' => $rating,
            ]);

            return $review;
        });
    }

    /**
     * Recompute average rating + review count for a store from visible reviews.
     * (Also runs automatically via Review model hooks on create/update/delete.)
     */
    public function recomputeStoreRating(int $storeId): void
    {
        Review::recomputeStoreAggregate($storeId);
    }

    protected function logAudit(User $actor, string $action, string $resourceType, int $resourceId, ?array $oldValue, ?array $newValue): void
    {
        AuditLog::create([
            'actor_id' => $actor->id,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'before_value' => $oldValue,
            'after_value' => $newValue,
            'ip_address' => request()->ip() ?? null,
            'user_agent' => request()->userAgent() ?? null,
            'created_at' => now(),
        ]);
    }
}