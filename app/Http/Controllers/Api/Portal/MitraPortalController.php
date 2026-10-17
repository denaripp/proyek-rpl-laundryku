<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Store;
use App\Models\StoreStaff;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MitraPortalController extends Controller
{
    /**
     * Resolve store for authenticated mitra (covers store owner AND active store staff).
     */
    protected function resolveStore(User $user): ?Store
    {
        // 1. Check if user is the store owner
        $owned = Store::where('owner_user_id', $user->id)->first();
        if ($owned) {
            return $owned;
        }

        // 2. Check if user is active staff of a store
        $staff = StoreStaff::where('user_id', $user->id)
            ->where('is_active', true)
            ->with('store')
            ->first();

        return $staff?->store;
    }

    /**
     * Get mitra store profile.
     * Accessible by owner and staff, including pending stores.
     */
    public function storeProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $store = $this->resolveStore($user);

        if (! $store) {
            return response()->json([
                'message' => 'Toko tidak ditemukan untuk akun ini.',
            ], 404);
        }

        $isOwner = (int) $store->owner_user_id === (int) $user->id;

        return response()->json([
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
                'slug' => $store->slug,
                'phone' => $store->phone,
                'address' => $store->address,
                'city' => $store->city,
                'province' => $store->province,
                'status' => $store->status,
                'is_active' => $store->is_active,
                'is_approved' => $store->isApproved(),
            ],
            'membership' => [
                'is_owner' => $isOwner,
                'is_staff' => ! $isOwner,
            ],
        ]);
    }

    /**
     * Get mitra store orders.
     * RESTRICTED: Mitra with pending stores cannot access orders.
     */
    public function orders(Request $request): JsonResponse
    {
        $user = $request->user();
        $store = $this->resolveStore($user);

        if (! $store) {
            return response()->json([
                'message' => 'Toko tidak ditemukan untuk akun ini.',
            ], 404);
        }

        // Pending store restriction
        if (! $store->isApproved()) {
            return response()->json([
                'message' => 'Toko Anda masih berstatus pending dan belum dapat mengakses daftar pesanan.',
            ], 403);
        }

        $orders = Order::where('store_id', $store->id)
            ->with(['customer:id,name,phone', 'items'])
            ->latest()
            ->paginate(15);

        return response()->json($orders);
    }
}
