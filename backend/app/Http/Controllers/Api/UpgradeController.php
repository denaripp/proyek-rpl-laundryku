<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CourierProfile;
use App\Models\Role;
use App\Models\Store;
use App\Models\StoreDocument;
use App\Models\UserRole;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UpgradeController extends Controller
{
    use ApiResponse;

    /**
     * Submit upgrade application to become a courier for a specific store (US-COU-01).
     */
    public function applyCourier(Request $request): JsonResponse
    {
        $user = $request->user();

        // Prevent duplicate pending or active application
        $existing = CourierProfile::where('user_id', $user->id)
            ->whereIn('verification_status', ['pending', 'approved'])
            ->first();

        if ($existing) {
            return $this->errorResponse(
                'DUPLICATE_APPLICATION',
                'Anda sudah memiliki pengajuan kurir yang sedang ditinjau atau telah disetujui.',
                null,
                422
            );
        }

        $validated = $request->validate([
            'store_id' => ['required', 'uuid', 'exists:stores,id'],
            'nik' => ['required', 'string', 'regex:/^[0-9]{16}$/'],
            'vehicle_type' => ['required', 'string', 'max:50'],
            'vehicle_plate' => ['required', 'string', 'max:20'],
            'ktp_file' => ['required', 'file', 'mimes:jpeg,png,webp,pdf', 'max:5120'], // Max 5MB
            'photo_file' => ['nullable', 'file', 'mimes:jpeg,png,webp', 'max:5120'],
        ], [
            'store_id.required' => 'Toko laundry yang ingin Anda lamar wajib dipilih.',
            'store_id.uuid' => 'Format ID toko tidak valid.',
            'store_id.exists' => 'Toko laundry yang dipilih tidak ditemukan.',
            'nik.required' => 'Nomor Induk Kependudukan (NIK) 16 digit wajib diisi.',
            'nik.regex' => 'Format NIK harus berupa 16 digit angka.',
            'vehicle_type.required' => 'Jenis kendaraan wajib dipilih.',
            'vehicle_plate.required' => 'Plat nomor kendaraan wajib diisi.',
            'ktp_file.required' => 'File foto/scan KTP wajib diunggah.',
            'ktp_file.mimes' => 'Format file KTP hanya boleh jpeg, png, webp, atau pdf.',
            'ktp_file.max' => 'Ukuran file KTP tidak boleh melebihi 5 MB.',
            'photo_file.mimes' => 'Format foto hanya boleh jpeg, png, atau webp.',
            'photo_file.max' => 'Ukuran foto tidak boleh melebihi 5 MB.',
        ]);

        // Verify store eligibility (must be approved and actively operating)
        $targetStore = Store::where('id', $validated['store_id'])
            ->where('approval_status', 'approved')
            ->where('operational_status', 'active')
            ->first();

        if (!$targetStore) {
            return $this->errorResponse(
                'STORE_NOT_ELIGIBLE',
                'Toko laundry yang dipilih tidak ditemukan, belum disetujui, atau sedang tidak aktif.',
                null,
                422
            );
        }

        // Upload KTP to private disk with randomized filename
        $ktpFile = $request->file('ktp_file');
        $ktpName = 'ktp_' . Str::random(40) . '.' . $ktpFile->getClientOriginalExtension();
        $ktpStorageKey = 'private/ktp/' . $ktpName;
        Storage::disk('local')->putFileAs('private/ktp', $ktpFile, $ktpName);

        // Upload photo if provided
        $photoStorageKey = null;
        if ($request->hasFile('photo_file')) {
            $photoFile = $request->file('photo_file');
            $photoName = 'courier_' . Str::random(40) . '.' . $photoFile->getClientOriginalExtension();
            $photoStorageKey = 'private/photos/' . $photoName;
            Storage::disk('local')->putFileAs('private/photos', $photoFile, $photoName);
        }

        $courier = DB::transaction(function () use ($user, $validated, $targetStore, $ktpStorageKey, $photoStorageKey) {
            $profile = CourierProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'store_id' => $targetStore->id,
                    'identity_encrypted' => $validated['nik'], // Encrypted via cast
                    'vehicle_type' => $validated['vehicle_type'],
                    'vehicle_plate_encrypted' => $validated['vehicle_plate'], // Encrypted via cast
                    'ktp_storage_key' => $ktpStorageKey,
                    'photo_storage_key' => $photoStorageKey,
                    'verification_status' => 'pending',
                    'rejection_reason' => null,
                    'status' => 'inactive',
                ]
            );

            $courierRole = Role::where('code', 'courier')->first();
            $roleId = $courierRole ? $courierRole->id : 2;

            UserRole::updateOrCreate(
                ['user_id' => $user->id, 'role_id' => $roleId],
                ['status' => 'pending', 'store_id' => $targetStore->id]
            );

            return $profile;
        });

        return $this->successResponse([
            'id' => $courier->id,
            'user_id' => $courier->user_id,
            'store_id' => $courier->store_id,
            'store_name' => $targetStore->name,
            'vehicle_type' => $courier->vehicle_type,
            'verification_status' => $courier->verification_status,
            'status' => $courier->status,
            'has_ktp' => !empty($courier->ktp_storage_key),
            'has_photo' => !empty($courier->photo_storage_key),
            'created_at' => $courier->created_at?->toIso8601String(),
        ], [
            'message' => "Pengajuan menjadi kurir toko {$targetStore->name} berhasil dikirim. Menunggu verifikasi admin toko.",
        ], 201);
    }

    /**
     * Get current user's courier application status.
     */
    public function courierStatus(Request $request): JsonResponse
    {
        $courier = CourierProfile::where('user_id', $request->user()->id)
            ->with('store')
            ->latest()
            ->first();

        if (!$courier) {
            return $this->successResponse([
                'applied' => false,
                'application' => null,
            ]);
        }

        return $this->successResponse([
            'applied' => true,
            'application' => [
                'id' => $courier->id,
                'store_id' => $courier->store_id,
                'store_name' => $courier->store?->name,
                'store_address' => $courier->store?->address_text,
                'vehicle_type' => $courier->vehicle_type,
                'verification_status' => $courier->verification_status,
                'rejection_reason' => $courier->rejection_reason,
                'status' => $courier->status,
                'has_ktp' => !empty($courier->ktp_storage_key),
                'created_at' => $courier->created_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Submit upgrade application to register a store / become store admin (US-STORE-01).
     */
    public function applyStore(Request $request): JsonResponse
    {
        $user = $request->user();

        // Prevent duplicate pending or approved store application
        $existing = Store::where('owner_user_id', $user->id)
            ->whereIn('approval_status', ['pending', 'approved'])
            ->first();

        if ($existing) {
            return $this->errorResponse(
                'DUPLICATE_APPLICATION',
                'Anda sudah memiliki pengajuan toko yang sedang ditinjau atau telah disetujui.',
                null,
                422
            );
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'address_text' => ['required', 'string'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'service_radius_km' => ['required', 'numeric', 'min:0.5', 'max:10.0'], // MAX 10 km constraint
            'opening_hours' => ['nullable'],
            'document_file' => ['required', 'file', 'mimes:jpeg,png,webp,pdf', 'max:5120'], // Max 5MB
            'document_type' => ['required', 'string', 'max:50'], // nib, siup, surat_izin
        ], [
            'name.required' => 'Nama toko wajib diisi.',
            'address_text.required' => 'Alamat lengkap toko wajib diisi.',
            'service_radius_km.required' => 'Radius layanan toko wajib diisi.',
            'service_radius_km.max' => 'Radius layanan maksimal adalah 10 km sesuai ketentuan platform.',
            'document_file.required' => 'Dokumen izin usaha/NIB wajib diunggah.',
            'document_file.mimes' => 'Format dokumen hanya boleh jpeg, png, webp, atau pdf.',
            'document_file.max' => 'Ukuran dokumen tidak boleh melebihi 5 MB.',
        ]);

        // Upload document to private storage
        $docFile = $request->file('document_file');
        $docName = 'store_doc_' . Str::random(40) . '.' . $docFile->getClientOriginalExtension();
        $docStorageKey = 'private/store_documents/' . $docName;
        Storage::disk('local')->putFileAs('private/store_documents', $docFile, $docName);

        // Normalize opening_hours if JSON string
        $openingHours = $validated['opening_hours'] ?? null;
        if (is_string($openingHours)) {
            $decoded = json_decode($openingHours, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $openingHours = $decoded;
            }
        }

        $store = DB::transaction(function () use ($user, $validated, $openingHours, $docStorageKey) {
            $store = Store::create([
                'owner_user_id' => $user->id,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'address_text' => $validated['address_text'],
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'service_radius_km' => $validated['service_radius_km'],
                'opening_hours' => $openingHours,
                'approval_status' => 'pending',
                'operational_status' => 'inactive',
            ]);

            StoreDocument::create([
                'store_id' => $store->id,
                'document_type' => $validated['document_type'],
                'storage_key' => $docStorageKey,
                'verification_status' => 'pending',
            ]);

            $storeRole = Role::where('code', 'store_admin')->first();
            $roleId = $storeRole ? $storeRole->id : 3;

            UserRole::create([
                'user_id' => $user->id,
                'role_id' => $roleId,
                'store_id' => $store->id,
                'status' => 'pending',
            ]);

            return $store;
        });

        return $this->successResponse([
            'id' => $store->id,
            'name' => $store->name,
            'address_text' => $store->address_text,
            'service_radius_km' => $store->service_radius_km,
            'approval_status' => $store->approval_status,
            'operational_status' => $store->operational_status,
            'created_at' => $store->created_at?->toIso8601String(),
        ], [
            'message' => 'Pendaftaran toko berhasil dikirim. Menunggu verifikasi dan persetujuan Super Admin.',
        ], 201);
    }

    /**
     * Get current user's store application status and documents.
     */
    public function storeStatus(Request $request): JsonResponse
    {
        $store = Store::where('owner_user_id', $request->user()->id)
            ->with(['documents'])
            ->latest()
            ->first();

        if (!$store) {
            return $this->successResponse([
                'applied' => false,
                'store' => null,
            ]);
        }

        return $this->successResponse([
            'applied' => true,
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
                'description' => $store->description,
                'address_text' => $store->address_text,
                'latitude' => $store->latitude,
                'longitude' => $store->longitude,
                'service_radius_km' => $store->service_radius_km,
                'opening_hours' => $store->opening_hours,
                'approval_status' => $store->approval_status,
                'operational_status' => $store->operational_status,
                'rejection_reason' => $store->rejection_reason,
                'approved_at' => $store->approved_at?->toIso8601String(),
                'documents' => $store->documents->map(fn ($doc) => [
                    'id' => $doc->id,
                    'document_type' => $doc->document_type,
                    'verification_status' => $doc->verification_status,
                ]),
                'created_at' => $store->created_at?->toIso8601String(),
            ],
        ]);
    }
}
