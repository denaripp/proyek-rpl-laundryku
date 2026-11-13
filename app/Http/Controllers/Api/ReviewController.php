<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Review;
use App\Models\Store;
use App\Services\Review\ReviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReviewController extends Controller
{
    protected ReviewService $reviewService;

    public function __construct(ReviewService $reviewService)
    {
        $this->reviewService = $reviewService;
    }

    /**
     * List visible reviews for a store.
     */
    public function index(Request $request, Store $store)
    {
        $reviews = $store->reviews()
            ->visible()
            ->with('customer:id,name')
            ->latest()
            ->paginate(15);

        return response()->json($reviews);
    }

    /**
     * Create a review for a completed order (1 review per order).
     */
     public function store(\App\Http\Requests\Review\CreateReviewRequest $request, Order $order)
     {
         $user = $request->user();

         Gate::forUser($user)->authorize('create', [Review::class, $order]);

         $validated = $request->validated();

         $review = $this->reviewService->createReview(
             $order,
             $user,
             (int) $validated['rating'],
             $validated['comment'] ?? null
         );

         return response()->json(array_merge($review->toArray(), [
             'success' => true,
             'data' => $review,
         ]), 201);
     }

    /**
     * GET /api/v1/orders/{order}/rating — get rating for an order.
     */
    public function orderRating(Request $request, Order $order)
    {
        $user = $request->user();

        // Authorization check (Anti-IDOR)
        $isCustomerOwner = $user && (int) $user->id === (int) $order->customer_id;
        $isStoreOwnerOrStaff = $user && $order->store && (
            (int) $user->id === (int) $order->store->owner_user_id
            || $order->store->staff()->where('user_id', $user->id)->where('is_active', true)->exists()
        );
        $isAdmin = $user && ($user->isSuperAdmin() || $user->hasPermissionTo('view_any_review'));

        if (! $isCustomerOwner && ! $isStoreOwnerOrStaff && ! $isAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki hak akses untuk melihat rating pesanan ini.',
            ], 403);
        }

        $review = $order->review;
        if (! $review) {
            return response()->json([
                'success' => true,
                'data' => null,
            ]);
        }

        // If review is hidden / moderated, hide from normal customer
        if (! $review->is_visible && $isCustomerOwner && ! $isAdmin) {
            return response()->json([
                'success' => true,
                'data' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => (string) $review->id,
                'order_id' => (string) $review->order_id,
                'order_number' => $order->order_number,
                'customer_id' => (string) ($review->customer_user_id ?? $review->customer_id ?? ''),
                'customer_name' => $review->customer?->name ?? 'Pelanggan',
                'store_id' => (string) $order->store_id,
                'store_name' => $order->store?->name ?? 'Toko',
                'score' => (int) $review->rating,
                'review' => $review->comment,
                'created_at' => $review->created_at?->toISOString() ?? now()->toISOString(),
                'updated_at' => $review->updated_at?->toISOString() ?? now()->toISOString(),
            ],
        ]);
    }

    /**
     * POST /api/v1/orders/{order}/rating — submit rating for an order.
     */
    public function storeRating(\App\Http\Requests\Review\CreateReviewRequest $request, Order $order)
    {
        return $this->store($request, $order);
    }
}