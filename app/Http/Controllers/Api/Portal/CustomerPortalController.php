<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerPortalController extends Controller
{
    /**
     * Get authenticated customer profile.
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->display_email,
                'display_email' => $user->display_email,
                'has_real_email' => $user->has_real_email,
                'phone' => $user->phone,
                'display_phone' => $user->display_phone,
                'role' => $user->role,
                'created_at' => $user->created_at,
            ],
        ]);
    }

    /**
     * Get customer order list.
     */
    public function orders(Request $request): JsonResponse
    {
        $user = $request->user();

        $orders = Order::where('customer_id', $user->id)
            ->with(['store:id,name,phone,address', 'items'])
            ->latest()
            ->paginate(15);

        return response()->json($orders);
    }
}
