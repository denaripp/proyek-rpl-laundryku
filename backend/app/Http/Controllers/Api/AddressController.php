<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    use ApiResponse;

    /**
     * List all addresses belonging to the authenticated customer.
     */
    public function index(Request $request): JsonResponse
    {
        $addresses = Address::where('user_id', $request->user()->id)
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->successResponse($addresses);
    }

    /**
     * Create a new address for the authenticated customer.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'address_text' => ['required', 'string'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_default' => ['nullable', 'boolean'],
        ], [
            'label.required' => 'Label alamat wajib diisi (contoh: Rumah, Kantor, Kos).',
            'address_text.required' => 'Alamat lengkap wajib diisi.',
            'latitude.between' => 'Nilai latitude harus antara -90 dan 90 derajat.',
            'longitude.between' => 'Nilai longitude harus antara -180 dan 180 derajat.',
        ]);

        $user = $request->user();
        $isFirst = Address::where('user_id', $user->id)->count() === 0;
        $isDefault = $isFirst || (!empty($validated['is_default']) && $validated['is_default']);

        $address = DB::transaction(function () use ($user, $validated, $isDefault) {
            if ($isDefault) {
                Address::where('user_id', $user->id)->update(['is_default' => false]);
            }

            return Address::create([
                'user_id' => $user->id,
                'label' => $validated['label'],
                'address_text' => $validated['address_text'],
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'is_default' => $isDefault,
            ]);
        });

        return $this->successResponse($address, ['message' => 'Alamat berhasil ditambahkan.'], 201);
    }

    /**
     * Show detail of an address.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $address = Address::find($id);

        if (!$address) {
            return $this->errorResponse('NOT_FOUND', 'Alamat tidak ditemukan.', null, 404);
        }

        if ($address->user_id !== $request->user()->id) {
            return $this->errorResponse('FORBIDDEN', 'Anda tidak memiliki akses ke alamat ini.', null, 403);
        }

        return $this->successResponse($address);
    }

    /**
     * Update an existing address.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $address = Address::find($id);

        if (!$address) {
            return $this->errorResponse('NOT_FOUND', 'Alamat tidak ditemukan.', null, 404);
        }

        if ($address->user_id !== $request->user()->id) {
            return $this->errorResponse('FORBIDDEN', 'Anda tidak memiliki akses untuk mengubah alamat ini.', null, 403);
        }

        $validated = $request->validate([
            'label' => ['sometimes', 'required', 'string', 'max:100'],
            'address_text' => ['sometimes', 'required', 'string'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();

        DB::transaction(function () use ($user, $address, $validated) {
            if (!empty($validated['is_default']) && $validated['is_default']) {
                Address::where('user_id', $user->id)->where('id', '!=', $address->id)->update(['is_default' => false]);
            }

            $address->update($validated);
        });

        return $this->successResponse($address->fresh(), ['message' => 'Alamat berhasil diperbarui.']);
    }

    /**
     * Delete an address.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $address = Address::find($id);

        if (!$address) {
            return $this->errorResponse('NOT_FOUND', 'Alamat tidak ditemukan.', null, 404);
        }

        if ($address->user_id !== $request->user()->id) {
            return $this->errorResponse('FORBIDDEN', 'Anda tidak memiliki akses untuk menghapus alamat ini.', null, 403);
        }

        $wasDefault = $address->is_default;
        $address->delete();

        // If the deleted address was default, make the most recent remaining address default
        if ($wasDefault) {
            $fallback = Address::where('user_id', $request->user()->id)->latest()->first();
            if ($fallback) {
                $fallback->is_default = true;
                $fallback->save();
            }
        }

        return $this->successResponse(['deleted' => true], ['message' => 'Alamat berhasil dihapus.']);
    }

    /**
     * Set specific address as default primary address.
     */
    public function setDefault(Request $request, string $id): JsonResponse
    {
        $address = Address::find($id);

        if (!$address) {
            return $this->errorResponse('NOT_FOUND', 'Alamat tidak ditemukan.', null, 404);
        }

        if ($address->user_id !== $request->user()->id) {
            return $this->errorResponse('FORBIDDEN', 'Anda tidak memiliki akses ke alamat ini.', null, 403);
        }

        $user = $request->user();

        DB::transaction(function () use ($user, $address) {
            Address::where('user_id', $user->id)->update(['is_default' => false]);
            $address->is_default = true;
            $address->save();
        });

        return $this->successResponse($address->fresh(), ['message' => 'Alamat utama berhasil diperbarui.']);
    }
}
