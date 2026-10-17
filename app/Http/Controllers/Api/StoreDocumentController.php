<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StoreDocument;
use App\Services\File\SecureFileUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * SECURITY (Tahap 19) — Store document upload endpoint.
 *
 * Uses SecureFileUpload for:
 *  - MIME server-side detection (finfo, NOT trust client)
 *  - Extension whitelist + ext/MIME consistency check
 *  - File size limit
 *  - Randomized filename
 *  - Private disk storage (no public URL)
 */
class StoreDocumentController extends Controller
{
    public function __construct(protected SecureFileUpload $uploader) {}

    /**
     * POST /api/store-owner/stores/{store}/documents
     * Multipart: file=@path; document_type=ktp|npwp|...
     */
    public function upload(Request $request, Store $store)
    {
        Gate::forUser($request->user())->authorize('update', $store);

        $validated = $request->validate([
            'document_type' => 'required|string|in:ktp,npwp,store_photo,other',
            'file' => 'required|file|max:5120', // 5MB hard cap (kbytes)
        ]);

        try {
            $meta = $this->uploader->store(
                $request->file('file'),
                'store_document',
                $store->id
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $doc = StoreDocument::create([
            'store_id' => $store->id,
            'document_type' => $validated['document_type'],
            'file_path' => $meta['path'],
            'original_filename' => $meta['original_filename'],
            'mime_type' => $meta['mime_type'],
            'file_size' => $meta['file_size'],
        ]);

        return response()->json($doc, 201);
    }

    /**
     * GET /api/store-owner/stores/{store}/documents
     * List documents for a store (owner/staff/admin only).
     */
    public function index(Request $request, Store $store)
    {
        // SECURITY (Tahap 20): viewOperations (strict ownership) instead
        // of generic view, to prevent IDOR.
        Gate::forUser($request->user())->authorize('viewOperations', $store);

        $docs = StoreDocument::where('store_id', $store->id)->latest()->get();

        return response()->json($docs);
    }

    /**
     * GET /api/store-owner/stores/{store}/documents/{doc}/download
     * Authorized download — streams the private file to the user.
     */
    public function download(Request $request, Store $store, StoreDocument $doc)
    {
        // SECURITY (Tahap 20): viewOperations (strict ownership) — these
        // are private identity documents (KTP/NPWP).
        Gate::forUser($request->user())->authorize('viewOperations', $store);

        if ($doc->store_id !== $store->id) {
            return response()->json(['error' => 'Document not found for this store.'], 404);
        }

        if (!Storage::disk('private')->exists($doc->file_path)) {
            return response()->json(['error' => 'File missing.'], 404);
        }

        return Storage::disk('private')->download(
            $doc->file_path,
            $doc->original_filename,
            ['Content-Type' => $doc->mime_type]
        );
    }

    /**
     * DELETE /api/store-owner/stores/{store}/documents/{doc}
     */
    public function destroy(Request $request, Store $store, StoreDocument $doc)
    {
        Gate::forUser($request->user())->authorize('update', $store);

        if ($doc->store_id !== $store->id) {
            return response()->json(['error' => 'Document not found for this store.'], 404);
        }

        $this->uploader->delete($doc->file_path);
        $doc->delete();

        return response()->json(['deleted' => true]);
    }
}