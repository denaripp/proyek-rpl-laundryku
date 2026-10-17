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

        return response()->json($review, 201);
    }
}