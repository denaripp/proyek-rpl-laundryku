<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CourierProfile;
use App\Models\StoreDocument;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    use ApiResponse;

    /**
     * Stream courier KTP document (accessible only by the courier or super_admin).
     */
    public function courierKtp(Request $request): StreamedResponse|JsonResponse
    {
        $user = $request->user();
        $courier = CourierProfile::where('user_id', $user->id)->first();

        if (!$courier || !$courier->ktp_storage_key) {
            return $this->errorResponse('NOT_FOUND', 'Dokumen KTP tidak ditemukan.', null, 404);
        }

        // Authorization check: user must be the courier owner or super_admin
        if ($courier->user_id !== $user->id && !$user->hasRole('super_admin')) {
            return $this->errorResponse('FORBIDDEN', 'Anda tidak memiliki akses ke dokumen ini.', null, 403);
        }

        if (!Storage::disk('local')->exists($courier->ktp_storage_key)) {
            return $this->errorResponse('NOT_FOUND', 'Berkas fisik KTP tidak ditemukan di penyimpanan.', null, 404);
        }

        $mime = Storage::disk('local')->mimeType($courier->ktp_storage_key) ?? 'application/octet-stream';

        return Storage::disk('local')->response($courier->ktp_storage_key, null, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * Stream store business document (accessible only by store owner or super_admin).
     */
    public function storeDocument(Request $request, string $documentId): StreamedResponse|JsonResponse
    {
        $user = $request->user();
        $document = StoreDocument::with('store')->find($documentId);

        if (!$document || !$document->storage_key) {
            return $this->errorResponse('NOT_FOUND', 'Dokumen usaha tidak ditemukan.', null, 404);
        }

        // Authorization check: user must be the store owner or super_admin
        if ($document->store->owner_user_id !== $user->id && !$user->hasRole('super_admin')) {
            return $this->errorResponse('FORBIDDEN', 'Anda tidak memiliki hak akses untuk melihat dokumen usaha ini.', null, 403);
        }

        if (!Storage::disk('local')->exists($document->storage_key)) {
            return $this->errorResponse('NOT_FOUND', 'Berkas fisik dokumen tidak ditemukan di penyimpanan.', null, 404);
        }

        $mime = Storage::disk('local')->mimeType($document->storage_key) ?? 'application/octet-stream';

        return Storage::disk('local')->response($document->storage_key, null, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
        ]);
    }
}
