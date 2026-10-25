<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\CreateAddressRequest;
use App\Http\Requests\Customer\UpdateProfileRequest;
use App\Models\Address;
use App\Models\Courier;
use App\Models\Store;
use App\Services\Maps\NominatimGeocodingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Tahap 16 — Customer profile + address management.
 */
class CustomerController extends Controller
{
    /**
     * Build customer profile data conforming to frontend ProfileData interface.
     */
    protected function buildProfileData($user): array
    {
        $roles = $user->roles ? $user->roles->pluck('name')->toArray() : [];
        if (empty($roles) && !empty($user->role)) {
            $roles = [$user->role];
        }

        $defaultAddress = Address::where('user_id', $user->id)
            ->where('is_default', true)
            ->first();

        $formattedDefaultAddress = $defaultAddress ? [
            'id' => (string) $defaultAddress->id,
            'user_id' => (string) $defaultAddress->user_id,
            'label' => $defaultAddress->label,
            'address_text' => $defaultAddress->address,
            'latitude' => $defaultAddress->latitude !== null ? (float) $defaultAddress->latitude : null,
            'longitude' => $defaultAddress->longitude !== null ? (float) $defaultAddress->longitude : null,
            'is_default' => true,
            'created_at' => $defaultAddress->created_at?->toISOString() ?? now()->toISOString(),
        ] : null;

        return [
            'id' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->display_email,
            'display_email' => $user->display_email,
            'has_real_email' => $user->has_real_email,
            'phone' => $user->phone,
            'display_phone' => $user->display_phone,
            'status' => $user->is_active ? 'active' : 'inactive',
            'avatar_url' => $user->avatar_url ?? null,
            'roles' => $roles,
            'profile' => [
                'date_of_birth' => $user->date_of_birth ?? null,
                'has_photo' => !empty($user->avatar_url),
                'avatar_url' => $user->avatar_url ?? null,
            ],
            'default_address' => $formattedDefaultAddress,
        ];
    }

    /**
     * GET /api/v1/profile & /api/customer/profile
     */
    public function profile(Request $request)
    {
        $user = $request->user();
        $profileData = $this->buildProfileData($user);

        return response()->json(array_merge([
            'success' => true,
            'data' => $profileData,
        ], [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->display_email,
            'display_email' => $user->display_email,
            'has_real_email' => $user->has_real_email,
            'phone' => $user->phone,
            'display_phone' => $user->display_phone,
            'is_active' => $user->is_active,
            'two_factor_enabled' => $user->two_factor_enabled ?? false,
            'last_login_at' => $user->last_login_at,
        ]));
    }

    /**
     * PATCH/PUT /api/v1/profile & /api/customer/profile
     */
    public function updateProfile(UpdateProfileRequest $request)
    {
        $user = $request->user();
        $validated = $request->validated();

        $fillable = array_intersect_key($validated, array_flip(['name', 'email', 'phone']));
        $user->fill($fillable);
        $user->save();

        $profileData = $this->buildProfileData($user);

        return response()->json(array_merge([
            'success' => true,
            'data' => $profileData,
        ], [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->display_email,
            'display_email' => $user->display_email,
            'has_real_email' => $user->has_real_email,
            'phone' => $user->phone,
            'display_phone' => $user->display_phone,
        ]));
    }

    /**
     * POST /api/v1/profile/photo
     */
    public function uploadPhoto(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'has_photo' => false,
                'avatar_url' => null,
                'photo_url' => null,
            ],
        ]);
    }

    /**
     * DELETE /api/v1/profile/photo
     */
    public function deletePhoto(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'has_photo' => false,
                'avatar_url' => null,
            ],
        ]);
    }

    /**
     * Format address model for API.
     */
    protected function formatAddress(Address $addr): array
    {
        return [
            'id' => (string) $addr->id,
            'user_id' => (string) $addr->user_id,
            'label' => $addr->label,
            'recipient_name' => $addr->recipient_name,
            'phone' => $addr->phone,
            'address' => $addr->address,
            'address_text' => $addr->address,
            'rt_rw' => $addr->rt_rw,
            'kelurahan' => $addr->kelurahan,
            'kecamatan' => $addr->kecamatan,
            'city' => $addr->city,
            'province' => $addr->province,
            'postal_code' => $addr->postal_code,
            'address_note' => $addr->address_note,
            'latitude' => $addr->latitude !== null ? (float) $addr->latitude : null,
            'longitude' => $addr->longitude !== null ? (float) $addr->longitude : null,
            'is_default' => (bool) $addr->is_default,
            'created_at' => $addr->created_at?->toISOString() ?? now()->toISOString(),
        ];
    }

    /**
     * GET /api/v1/addresses & /api/customer/addresses
     */
    public function listAddresses(Request $request)
    {
        $user = $request->user();
        $addresses = Address::where('user_id', $user->id)
            ->orderByDesc('is_default')
            ->orderBy('created_at', 'desc')
            ->get();

        $formatted = $addresses->map(fn($a) => $this->formatAddress($a))->values();

        return response()->json([
            'success' => true,
            'data' => $formatted,
            'addresses' => $addresses,
        ]);
    }

    /**
     * POST /api/v1/addresses & /api/customer/addresses
     */
    public function storeAddress(CreateAddressRequest $request)
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
                'address' => $data['address'] ?? ($data['address_text'] ?? ''),
                'latitude' => isset($data['latitude']) && $data['latitude'] !== '' ? (float) $data['latitude'] : null,
                'longitude' => isset($data['longitude']) && $data['longitude'] !== '' ? (float) $data['longitude'] : null,
                'is_default' => $data['is_default'] ?? false,
                'recipient_name' => $data['recipient_name'] ?? ($user->name ?? 'Pelanggan'),
                'phone' => $data['phone'] ?? ($user->phone ?? ''),
                'rt_rw' => $data['rt_rw'] ?? null,
                'kelurahan' => $data['kelurahan'] ?? null,
                'kecamatan' => $data['kecamatan'] ?? null,
                'city' => $data['city'] ?? 'Jakarta',
                'province' => $data['province'] ?? 'DKI Jakarta',
                'postal_code' => $data['postal_code'] ?? '10000',
                'address_note' => $data['address_note'] ?? null,
            ]);

            $formatted = $this->formatAddress($address);

            return response()->json(array_merge($address->toArray(), [
                'success' => true,
                'data' => $formatted,
            ]), 201);
        });
    }

    /**
     * PUT /api/v1/addresses/{address}
     */
    public function updateAddress(Request $request, Address $address)
    {
        $user = $request->user();
        if ((int) $address->user_id !== (int) $user->id) {
            return response()->json(['error' => 'Forbidden.'], 403);
        }

        $data = $request->all();
        if (isset($data['address_text']) && !isset($data['address'])) {
            $data['address'] = $data['address_text'];
        }

        if (!empty($data['is_default'])) {
            Address::where('user_id', $user->id)->where('id', '!=', $address->id)->update(['is_default' => false]);
        }

        $address->update(array_filter([
            'label' => $data['label'] ?? null,
            'address' => $data['address'] ?? null,
            'recipient_name' => $data['recipient_name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'rt_rw' => $data['rt_rw'] ?? null,
            'kelurahan' => $data['kelurahan'] ?? null,
            'kecamatan' => $data['kecamatan'] ?? null,
            'city' => $data['city'] ?? null,
            'province' => $data['province'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'address_note' => $data['address_note'] ?? null,
            'latitude' => isset($data['latitude']) && $data['latitude'] !== '' ? (float) $data['latitude'] : null,
            'longitude' => isset($data['longitude']) && $data['longitude'] !== '' ? (float) $data['longitude'] : null,
            'is_default' => isset($data['is_default']) ? (bool) $data['is_default'] : null,
        ], fn($v) => !is_null($v)));

        $formatted = $this->formatAddress($address);

        return response()->json([
            'success' => true,
            'data' => $formatted,
        ]);
    }

    /**
     * POST /api/v1/geocode/reverse
     */
    public function reverseGeocode(Request $request, NominatimGeocodingService $geocodingService)
    {
        if ($request->has('lat') && !$request->has('latitude')) {
            $request->merge(['latitude' => $request->input('lat')]);
        }
        if ($request->has('lng') && !$request->has('longitude')) {
            $request->merge(['longitude' => $request->input('lng')]);
        }

        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $lat = (float) $validated['latitude'];
        $lng = (float) $validated['longitude'];

        $result = $geocodingService->reverseGeocodeStructured($lat, $lng);

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mendapatkan rincian alamat dari lokasi GPS. Silakan lengkapi alamat secara manual.',
                'error' => [
                    'code' => 'GEOCODE_FAILED',
                    'message' => 'Gagal mendapatkan rincian alamat dari lokasi GPS. Silakan lengkapi alamat secara manual.',
                ],
            ], 502);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * POST /api/v1/addresses/{address}/set-default
     */
    public function setDefaultAddress(Request $request, Address $address)
    {
        $user = $request->user();
        if ((int) $address->user_id !== (int) $user->id) {
            return response()->json(['error' => 'Forbidden.'], 403);
        }

        DB::transaction(function () use ($user, $address) {
            Address::where('user_id', $user->id)->update(['is_default' => false]);
            $address->update(['is_default' => true]);
        });

        $formatted = $this->formatAddress($address);

        return response()->json([
            'success' => true,
            'data' => $formatted,
        ]);
    }

    /**
     * DELETE /api/v1/addresses/{address} & /api/customer/addresses/{address}
     */
    public function destroyAddress(Request $request, Address $address)
    {
        $user = $request->user();

        if ((int) $address->user_id !== (int) $user->id) {
            return response()->json(['error' => 'Forbidden.'], 403);
        }

        $address->delete();
        return response()->json([
            'success' => true,
            'deleted' => true,
        ]);
    }

    /**
     * GET /api/v1/upgrades/courier
     */
    public function courierUpgradeStatus(Request $request)
    {
        $user = $request->user();
        $courier = Courier::where('user_id', $user->id)->first();

        if (!$courier) {
            return response()->json([
                'success' => true,
                'data' => [
                    'applied' => false,
                    'application' => null,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'applied' => true,
                'application' => [
                    'id' => (string) $courier->id,
                    'store_id' => $courier->store_id ? (string) $courier->store_id : null,
                    'store_name' => $courier->store?->name,
                    'vehicle_type' => $courier->vehicle_type ?? 'motorcycle',
                    'verification_status' => $courier->is_verified ? 'verified' : 'pending',
                    'status' => $courier->is_verified ? 'approved' : 'pending',
                    'has_ktp' => true,
                    'created_at' => $courier->created_at?->toISOString() ?? now()->toISOString(),
                ],
            ],
        ]);
    }

    /**
     * POST /api/v1/upgrades/courier
     */
    public function applyCourierUpgrade(Request $request)
    {
        $user = $request->user();
        $courier = Courier::firstOrCreate(
            ['user_id' => $user->id],
            [
                'vehicle_type' => $request->input('vehicle_type', 'motorcycle'),
                'vehicle_plate' => $request->input('vehicle_plate', 'B 1234 ABC'),
                'store_id' => $request->input('store_id'),
                'is_verified' => false,
                'is_available' => true,
            ]
        );

        return response()->json([
            'success' => true,
            'data' => [
                'applied' => true,
                'application' => [
                    'id' => (string) $courier->id,
                    'vehicle_type' => $courier->vehicle_type,
                    'verification_status' => 'pending',
                    'status' => 'pending',
                    'created_at' => $courier->created_at?->toISOString() ?? now()->toISOString(),
                ],
            ],
        ], 201);
    }

    /**
     * GET /api/v1/upgrades/store
     */
    public function storeUpgradeStatus(Request $request)
    {
        $user = $request->user();
        $store = Store::where('owner_user_id', $user->id)->first();

        if (!$store) {
            return response()->json([
                'success' => true,
                'data' => [
                    'applied' => false,
                    'store' => null,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'applied' => true,
                'store' => [
                    'id' => (string) $store->id,
                    'name' => $store->name,
                    'description' => $store->description,
                    'address_text' => $store->address,
                    'latitude' => $store->latitude !== null ? (float) $store->latitude : null,
                    'longitude' => $store->longitude !== null ? (float) $store->longitude : null,
                    'service_radius_km' => (float) ($store->service_radius_km ?? 10),
                    'opening_hours' => $store->operational_hours,
                    'approval_status' => $store->status ?? 'pending',
                    'operational_status' => $store->is_active ? 'active' : 'inactive',
                    'documents' => [],
                    'created_at' => $store->created_at?->toISOString() ?? now()->toISOString(),
                ],
            ],
        ]);
    }

    /**
     * POST /api/v1/upgrades/store
     */
    public function applyStoreUpgrade(Request $request)
    {
        $user = $request->user();
        $store = Store::firstOrCreate(
            ['owner_user_id' => $user->id],
            [
                'name' => $request->input('name', 'Laundry ' . $user->name),
                'address' => $request->input('address', 'Alamat Mitra'),
                'status' => 'submitted',
                'is_active' => false,
            ]
        );

        return response()->json([
            'success' => true,
            'data' => [
                'applied' => true,
                'store' => [
                    'id' => (string) $store->id,
                    'name' => $store->name,
                    'approval_status' => 'submitted',
                    'created_at' => $store->created_at?->toISOString() ?? now()->toISOString(),
                ],
            ],
        ], 201);
    }
}
