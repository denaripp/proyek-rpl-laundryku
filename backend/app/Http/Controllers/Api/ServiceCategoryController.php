<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServiceCategory;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class ServiceCategoryController extends Controller
{
    use ApiResponse;

    /**
     * Get list of all active service categories (FR-MKT-08).
     */
    public function index(): JsonResponse
    {
        $categories = ServiceCategory::where('status', 'active')
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'slug', 'status']);

        return $this->successResponse(
            data: $categories,
            meta: [
                'total' => $categories->count(),
            ]
        );
    }
}
