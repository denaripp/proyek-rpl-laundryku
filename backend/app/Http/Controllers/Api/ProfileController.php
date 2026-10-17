<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserProfile;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    use ApiResponse;

    /**
     * Get authenticated user profile with active role & default address.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load(['roles', 'profile', 'defaultAddress']);

        return $this->successResponse([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $user->status,
            'verified_at' => $user->verified_at?->toIso8601String(),
            'avatar_url' => $user->profile?->avatar_url,
            'roles' => $user->roles->filter(fn ($r) => ($r->pivot->status ?? null) === 'active')->pluck('code')->values(),
            'profile' => [
                'date_of_birth' => $user->profile?->date_of_birth?->format('Y-m-d'),
                'has_photo' => !empty($user->profile?->profile_photo_key),
                'avatar_url' => $user->profile?->avatar_url,
            ],
            'default_address' => $user->defaultAddress,
        ]);
    }

    /**
     * Update customer profile basic info.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before:today'],
        ], [
            'name.required' => 'Nama lengkap tidak boleh kosong.',
            'date_of_birth.date' => 'Format tanggal lahir tidak valid.',
            'date_of_birth.before' => 'Tanggal lahir harus sebelum hari ini.',
        ]);

        $user = $request->user();

        if (isset($validated['name'])) {
            $user->name = $validated['name'];
            $user->save();
        }

        if (array_key_exists('date_of_birth', $validated)) {
            UserProfile::updateOrCreate(
                ['user_id' => $user->id],
                ['date_of_birth' => $validated['date_of_birth']]
            );
        }

        $user->load(['roles', 'profile', 'defaultAddress']);

        return $this->successResponse([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $user->status,
            'verified_at' => $user->verified_at?->toIso8601String(),
            'avatar_url' => $user->profile?->avatar_url,
            'roles' => $user->roles->filter(fn ($r) => ($r->pivot->status ?? null) === 'active')->pluck('code')->values(),
            'profile' => [
                'date_of_birth' => $user->profile?->date_of_birth?->format('Y-m-d'),
                'has_photo' => !empty($user->profile?->profile_photo_key),
                'avatar_url' => $user->profile?->avatar_url,
            ],
            'default_address' => $user->defaultAddress,
        ], [
            'message' => 'Profil berhasil diperbarui.',
        ]);
    }

    /**
     * Upload and process profile avatar to public storage.
     */
    public function uploadPhoto(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => [
                'required',
                'file',
                'mimes:jpeg,png,webp',
                'max:2048', // 2 MB max
            ],
        ], [
            'photo.required' => 'File foto profil wajib disertakan.',
            'photo.file' => 'Upload harus berupa file dokumen/gambar yang valid.',
            'photo.mimes' => 'Format foto hanya boleh jpeg, png, atau webp.',
            'photo.max' => 'Ukuran foto profil maksimal 2 MB.',
        ]);

        $file = $request->file('photo');

        // Verify image with GD and extract dimensions
        $imageContents = file_get_contents($file->getRealPath());
        $imageResource = @imagecreatefromstring($imageContents);

        if (!$imageResource) {
            return $this->errorResponse(
                'INVALID_IMAGE',
                'File bukan format gambar yang valid atau file rusak.',
                null,
                422
            );
        }

        $width = imagesx($imageResource);
        $height = imagesy($imageResource);

        if ($width < 256 || $height < 256) {
            imagedestroy($imageResource);
            return $this->errorResponse(
                'IMAGE_DIMENSION_TOO_SMALL',
                "Dimensi gambar terlalu kecil ($width x $height). Minimal 256x256 piksel.",
                null,
                422
            );
        }

        // Re-encode image to clean truecolor JPEG to strip EXIF, GPS, and metadata
        $canvas = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
        imagecopyresampled($canvas, $imageResource, 0, 0, 0, 0, $width, $height, $width, $height);

        ob_start();
        imagejpeg($canvas, null, 90);
        $cleanData = ob_get_clean();

        imagedestroy($imageResource);
        imagedestroy($canvas);

        // Generate randomized filename
        $filename = 'avatar_' . Str::uuid() . '.jpg';
        $storagePath = 'avatars/' . $filename;

        // Save to public disk
        Storage::disk('public')->put($storagePath, $cleanData);

        $user = $request->user();
        $profile = UserProfile::firstOrNew(['user_id' => $user->id]);

        // Delete old photo if exists
        if ($profile->profile_photo_key && Storage::disk('public')->exists($profile->profile_photo_key)) {
            Storage::disk('public')->delete($profile->profile_photo_key);
        }

        $profile->profile_photo_key = $storagePath;
        $profile->save();
        $profile->touch(); // updates updated_at timestamp

        return $this->successResponse([
            'has_photo' => true,
            'avatar_url' => $profile->avatar_url,
            'photo_url' => $profile->avatar_url,
        ], [
            'message' => 'Foto profil berhasil diperbarui.',
        ]);
    }

    /**
     * Delete profile photo / reset to initial fallback.
     */
    public function deletePhoto(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->profile;

        if ($profile && $profile->profile_photo_key) {
            if (Storage::disk('public')->exists($profile->profile_photo_key)) {
                Storage::disk('public')->delete($profile->profile_photo_key);
            }
            $profile->profile_photo_key = null;
            $profile->save();
            $profile->touch();
        }

        return $this->successResponse([
            'has_photo' => false,
            'avatar_url' => null,
            'photo_url' => null,
        ], [
            'message' => 'Foto profil berhasil dihapus.',
        ]);
    }

    /**
     * Stream profile photo.
     */
    public function streamPhoto(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse|JsonResponse
    {
        $user = $request->user();
        $profile = $user->profile;

        if (!$profile || !$profile->profile_photo_key) {
            return $this->errorResponse('NOT_FOUND', 'Foto profil tidak ditemukan.', null, 404);
        }

        $disk = Storage::disk('public')->exists($profile->profile_photo_key)
            ? Storage::disk('public')
            : Storage::disk('local');

        if (!$disk->exists($profile->profile_photo_key)) {
            return $this->errorResponse('NOT_FOUND', 'Foto profil tidak ditemukan.', null, 404);
        }

        $mime = $disk->mimeType($profile->profile_photo_key) ?? 'image/jpeg';

        return $disk->response($profile->profile_photo_key, null, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
