<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPortalController extends Controller
{
    /**
     * Get platform overview metrics for admin.
     */
    public function overview(Request $request): JsonResponse
    {
        return response()->json([
            'metrics' => [
                'total_users' => User::count(),
                'total_stores' => Store::count(),
                'pending_stores' => Store::where('status', 'submitted')->count(),
                'approved_stores' => Store::where('status', 'approved')->count(),
                'total_orders' => Order::count(),
            ],
        ]);
    }
}
