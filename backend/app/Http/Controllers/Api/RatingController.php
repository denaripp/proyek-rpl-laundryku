<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Rating;
use App\Models\Store;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RatingController extends Controller
{
    use ApiResponse;

    /**
     * Customer submits a rating & review for a completed order (FR-RATE-01, FR-RATE-02, FR-RATE-03, US-CUS-12).
     */
    public function store(Request $request, string $orderId): JsonResponse
    {
        $user = $request->user();
        $order = Order::with('store')->find($orderId);

        if (!$order) {
            return $this->errorResponse('NOT_FOUND', 'Pesanan tidak ditemukan.', null, 404);
        }

        // Authorization: Only customer who owns the order can submit rating
        if ($order->customer_id !== $user->id && !$user->hasRole('super_admin')) {
            return $this->errorResponse('FORBIDDEN', 'Hanya pelanggan pemilik pesanan yang dapat memberikan rating.', null, 403);
        }

        // Business Rule: Customer can ONLY give rating after order is COMPLETED
        if ($order->status !== 'COMPLETED') {
            return $this->errorResponse(
                'ORDER_NOT_COMPLETED',
                "Rating hanya dapat diberikan setelah pesanan berstatus Selesai (COMPLETED). Status pesanan saat ini: '{$order->status}'.",
                [
                    'current_status' => $order->status,
                    'required_status' => 'COMPLETED',
                ],
                422
            );
        }

        // Constraint: Prevent duplicate rating for the same order (1 active rating per order)
        if (Rating::where('order_id', $order->id)->exists()) {
            return $this->errorResponse(
                'DUPLICATE_RATING',
                'Pesanan ini sudah pernah diberi rating. Satu pesanan hanya dapat memiliki 1 rating ulasan.',
                null,
                409
            );
        }

        $validator = Validator::make($request->all(), [
            'score' => ['required', 'integer', 'min:1', 'max:5'],
            'review' => ['nullable', 'string', 'max:1000'],
        ], [
            'score.required' => 'Nilai rating bintang (1-5) wajib dipilih.',
            'score.integer' => 'Nilai rating harus berupa bilangan bulat antara 1 sampai 5.',
            'score.min' => 'Rating minimal adalah 1 bintang.',
            'score.max' => 'Rating maksimal adalah 5 bintang.',
            'review.max' => 'Ulasan teks maksimal 1000 karakter.',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                'VALIDATION_ERROR',
                'Data penilaian rating tidak valid.',
                $validator->errors()->toArray(),
                422
            );
        }

        $validated = $validator->validated();

        try {
            $rating = DB::transaction(function () use ($user, $order, $validated) {
                $rating = Rating::create([
                    'order_id' => $order->id,
                    'customer_id' => $user->id,
                    'store_id' => $order->store_id,
                    'score' => (int) $validated['score'],
                    'review' => isset($validated['review']) ? trim($validated['review']) : null,
                ]);

                // In-app notification to store owner
                if ($order->store && $order->store->owner_user_id) {
                    Notification::create([
                        'user_id' => $order->store->owner_user_id,
                        'channel' => 'in_app',
                        'event_type' => 'rating_submitted',
                        'title' => 'Ulasan & Rating Baru',
                        'message' => "Ulasan baru ({$rating->score} bintang) diterima untuk pesanan {$order->order_number}.",
                        'payload' => [
                            'rating_id' => $rating->id,
                            'order_id' => $order->id,
                            'order_number' => $order->order_number,
                            'score' => $rating->score,
                            'review' => $rating->review,
                        ],
                    ]);
                }


                return $rating->load(['order', 'customer', 'store']);
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            return $this->errorResponse(
                'DUPLICATE_RATING',
                'Pesanan ini sudah pernah diberi rating. Sistem mencegah rating ganda.',
                null,
                409
            );
        }

        return $this->successResponse(
            data: $this->formatRating($rating),
            meta: ['message' => 'Terima kasih! Penilaian dan ulasan Anda berhasil disimpan.'],
            status: 201
        );
    }


    /**
     * Get rating for a specific order (customer or store owner).
     */
    public function showByOrder(Request $request, string $orderId): JsonResponse
    {
        $user = $request->user();
        $order = Order::find($orderId);

        if (!$order) {
            return $this->errorResponse('NOT_FOUND', 'Pesanan tidak ditemukan.', null, 404);
        }

        $rating = Rating::where('order_id', $order->id)
            ->with(['customer', 'store'])
            ->first();

        if (!$rating) {
            return $this->successResponse(
                data: null,
                meta: ['has_rated' => false, 'order_status' => $order->status]
            );
        }

        return $this->successResponse(
            data: $this->formatRating($rating),
            meta: ['has_rated' => true]
        );
    }

    /**
     * Public: Get ratings and statistical summary for a store (FR-MKT-08, FR-RATE-02).
     */
    public function storeRatings(Request $request, string $storeId): JsonResponse
    {
        $store = Store::where('id', $storeId)
            ->where('approval_status', 'approved')
            ->first();

        if (!$store) {
            return $this->errorResponse('NOT_FOUND', 'Toko tidak ditemukan.', null, 404);
        }

        $query = Rating::where('store_id', $store->id)
            ->with(['customer.profile']);

        if ($request->has('score') && in_array((int) $request->query('score'), [1, 2, 3, 4, 5])) {
            $query->where('score', (int) $request->query('score'));
        }

        if ($request->boolean('has_review')) {
            $query->whereNotNull('review')->where('review', '!=', '');
        }

        $perPage = min((int) $request->query('per_page', 10), 50);
        $ratings = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Calculate aggregate statistics
        $allRatings = Rating::where('store_id', $store->id)->get();
        $totalCount = $allRatings->count();
        $averageScore = $totalCount > 0 ? round((float) $allRatings->avg('score'), 1) : 0.0;

        $breakdown = [
            '5' => $allRatings->where('score', 5)->count(),
            '4' => $allRatings->where('score', 4)->count(),
            '3' => $allRatings->where('score', 3)->count(),
            '2' => $allRatings->where('score', 2)->count(),
            '1' => $allRatings->where('score', 1)->count(),
        ];

        return $this->successResponse(
            data: collect($ratings->items())->map(fn ($r) => $this->formatRating($r)),
            meta: [
                'current_page' => $ratings->currentPage(),
                'last_page' => $ratings->lastPage(),
                'per_page' => $ratings->perPage(),
                'total' => $ratings->total(),
                'summary' => [
                    'average_score' => $averageScore,
                    'total_ratings' => $totalCount,
                    'breakdown' => $breakdown,
                ],
            ]
        );
    }

    /**
     * Store Admin: View all ratings & reviews for their store.
     */
    public function storeAdminIndex(Request $request): JsonResponse
    {
        $user = $request->user();
        $storeIds = Store::where('owner_user_id', $user->id)->pluck('id');

        if ($storeIds->isEmpty() && !$user->hasRole('super_admin')) {
            return $this->errorResponse('FORBIDDEN', 'Anda tidak memiliki toko terdaftar.', null, 403);
        }

        $query = Rating::with(['order', 'customer.profile']);

        if (!$user->hasRole('super_admin')) {
            $query->whereIn('store_id', $storeIds);
        }

        if ($request->has('score') && in_array((int) $request->query('score'), [1, 2, 3, 4, 5])) {
            $query->where('score', (int) $request->query('score'));
        }

        $perPage = min((int) $request->query('per_page', 15), 50);
        $ratings = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $storeId = $storeIds->first();
        $allRatings = Rating::when($storeId, fn ($q) => $q->where('store_id', $storeId))->get();
        $totalCount = $allRatings->count();
        $averageScore = $totalCount > 0 ? round((float) $allRatings->avg('score'), 1) : 0.0;

        return $this->successResponse(
            data: collect($ratings->items())->map(fn ($r) => $this->formatRating($r)),
            meta: [
                'current_page' => $ratings->currentPage(),
                'last_page' => $ratings->lastPage(),
                'per_page' => $ratings->perPage(),
                'total' => $ratings->total(),
                'summary' => [
                    'average_score' => $averageScore,
                    'total_ratings' => $totalCount,
                ],
            ]
        );
    }

    /**
     * Format Rating model into standard response.
     */
    protected function formatRating(Rating $rating): array
    {
        return [
            'id' => $rating->id,
            'order_id' => $rating->order_id,
            'order_number' => $rating->order?->order_number,
            'customer_id' => $rating->customer_id,
            'customer_name' => $rating->customer?->name,
            'customer_avatar' => $rating->customer?->profile?->avatar_url ?? null,
            'store_id' => $rating->store_id,
            'store_name' => $rating->store?->name,
            'score' => (int) $rating->score,
            'review' => $rating->review,
            'created_at' => $rating->created_at?->toIso8601String(),
            'updated_at' => $rating->updated_at?->toIso8601String(),
        ];
    }
}
