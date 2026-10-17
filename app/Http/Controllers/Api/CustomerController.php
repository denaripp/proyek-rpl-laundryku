<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\CreateAddressRequest;
use App\Http\Requests\Customer\UpdateProfileRequest;
use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Tahap 16 — Customer profile + address management.
 */
class CustomerController extends Controller
{
    /**
     * GET /api/customer/profile
     */
    public function profile(Request $request)
    {
        $user = $request->user();
        return response()->json($user->only([
            'id', 'name', 'email', 'phone', 'is_active', 'two_factor_enabled', 'last_login_at',
        ]));
    }

    /**
     * PATCH /api/customer/profile
     */
    public function updateProfile(UpdateProfileRequest $request)
    {
        $user = $request->user();
        $user->fill($request->validated());
        $user->save();

        return response()->json($user->only(['id', 'name', 'email', 'phone']));
    }

    /**
     * GET /api/customer/addresses
     */
    public function listAddresses(Request $request)
    {
        $user = $request->user();
        $addresses = Address::where('user_id', $user->id)
            ->orderByDesc('is_default')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($addresses);
    }

    /**
     * POST /api/customer/addresses
     */
    public function storeAddress(\App\Http\Requests\Customer\CreateAddressRequest $request)
    {
        $user = $request->user();
        $data = $request->validated();

        return DB::transaction(function () use ($user, $data) {
            if (!empty($data['is_default'])) {
                Address::where('user_id', $user->id)->update(['is_default' => false]);
            }

            $address = Address::create([
                'user_id' => $user->id,
                'label' => $data['label'],
                'address' => $data['address'],
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'is_default' => $data['is_default'] ?? false,
                'recipient_name' => $user->name,
                'phone' => $user->phone ?? '',
                'city' => $data['city'] ?? 'Jakarta',
                'province' => $data['province'] ?? 'DKI Jakarta',
                'postal_code' => $data['postal_code'] ?? '10000',
            ]);

            return response()->json($address, 201);
        });
    }

    /**
     * DELETE /api/customer/addresses/{address}
     */
    public function destroyAddress(Request $request, Address $address)
    {
        $user = $request->user();

        // SECURITY (Tahap 19): ownership re-check — address harus milik user.
        // Defense against IDOR: route-model binding memberika ID dari URL,
        // tapi kita wajib verifikasi address->user_id == user->id.
        if ((int) $address->user_id !== (int) $user->id) {
            return response()->json(['error' => 'Forbidden.'], 403);
        }

        $address->delete();
        return response()->json(['deleted' => true]);
    }
}
