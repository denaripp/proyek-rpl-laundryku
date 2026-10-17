<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\ComplaintAttachment;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Store;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ComplaintController extends Controller
{
    use ApiResponse;

    /**
     * List complaints for authenticated customer (FR-COM-01).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Complaint::with(['order', 'store', 'attachments'])
            ->where('customer_id', $user->id);

        if ($request->has('status') && !empty($request->query('status'))) {
            $query->where('status', $request->query('status'));
        }

        if ($request->has('order_id') && !empty($request->query('order_id'))) {
            $query->where('order_id', $request->query('order_id'));
        }

        $perPage = min((int) $request->query('per_page', 15), 50);
        $complaints = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->successResponse(
            data: collect($complaints->items())->map(fn ($c) => $this->formatComplaint($c)),
            meta: [
                'current_page' => $complaints->currentPage(),
                'last_page' => $complaints->lastPage(),
                'per_page' => $complaints->perPage(),
                'total' => $complaints->total(),
            ]
        );
    }

    /**
     * Customer creates a complaint linked to a specific order (FR-COM-01, FR-COM-02, US-CUS-12).
     */
    public function store(Request $request, string $orderId): JsonResponse
    {
        $user = $request->user();
        $order = Order::with('store')->find($orderId);

        if (!$order) {
            return $this->errorResponse('NOT_FOUND', 'Pesanan tidak ditemukan.', null, 404);
        }

        if ($order->customer_id !== $user->id && !$user->hasRole('super_admin')) {
            return $this->errorResponse('FORBIDDEN', 'Hanya pelanggan pemilik pesanan yang dapat mengajukan komplain.', null, 403);
        }

        $validator = Validator::make($request->all(), [
            'category' => ['required', 'string', 'in:DAMAGED_ITEM,LOST_ITEM,LATE_DELIVERY,SERVICE_QUALITY,INCORRECT_BILLING,OTHER'],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'], // max 5MB each
        ], [
            'category.required' => 'Kategori komplain wajib dipilih.',
            'category.in' => 'Kategori komplain tidak valid.',
            'description.required' => 'Deskripsi keluhan wajib diisi.',
            'description.min' => 'Deskripsi keluhan minimal 10 karakter agar toko dapat memahami kendala.',
            'description.max' => 'Deskripsi keluhan maksimal 2000 karakter.',
            'photos.max' => 'Maksimal 5 foto bukti yang dapat dilampirkan.',
            'photos.*.image' => 'Semua lampiran bukti harus berupa file gambar (JPG, PNG, WebP).',
            'photos.*.max' => 'Ukuran setiap foto bukti maksimal 5 MB.',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                'VALIDATION_ERROR',
                'Data formulir komplain tidak valid.',
                $validator->errors()->toArray(),
                422
            );
        }

        $validated = $validator->validated();

        $complaint = DB::transaction(function () use ($user, $order, $validated, $request) {
            $complaint = Complaint::create([
                'order_id' => $order->id,
                'customer_id' => $user->id,
                'store_id' => $order->store_id,
                'category' => $validated['category'],
                'description' => $validated['description'],
                'status' => 'OPEN',
                'resolution_note' => null,
            ]);

            // Save photo attachments if present
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $ext = $photo->getClientOriginalExtension();
                    $filename = Str::uuid() . '.' . $ext;
                    $path = $photo->storeAs('private/complaints/' . $complaint->id, $filename, 'local');

                    ComplaintAttachment::create([
                        'complaint_id' => $complaint->id,
                        'storage_key' => $path,
                        'file_name' => $photo->getClientOriginalName(),
                        'file_size' => $photo->getSize(),
                        'mime_type' => $photo->getMimeType(),
                    ]);
                }
            }

            // Multi-channel notification to store owner (FR-NOTIF)
            try {
                app(\App\Services\Notification\NotificationService::class)->notifyComplaintUpdated($complaint, 'OPEN');
            } catch (\Throwable $e) {
                // Ignore failure
            }

            return $complaint->load(['order', 'store', 'customer', 'attachments']);
        });

        return $this->successResponse(
            data: $this->formatComplaint($complaint),
            meta: ['message' => 'Komplain berhasil diajukan dan telah diteruskan ke admin toko.'],
            status: 201
        );
    }



    /**
     * Get complaint detail (customer, store admin, super admin).
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $complaint = Complaint::with(['order', 'store', 'customer', 'resolver', 'attachments'])->find($id);

        if (!$complaint) {
            return $this->errorResponse('NOT_FOUND', 'Data komplain tidak ditemukan.', null, 404);
        }

        $this->authorizeComplaintAccess($complaint, $user);

        return $this->successResponse(
            data: $this->formatComplaint($complaint)
        );
    }

    /**
     * Stream private attachment image.
     */
    public function streamAttachment(Request $request, string $id, string $attachmentId)
    {
        $user = $request->user();
        $complaint = Complaint::with('store')->find($id);

        if (!$complaint) {
            return $this->errorResponse('NOT_FOUND', 'Data komplain tidak ditemukan.', null, 404);
        }

        $this->authorizeComplaintAccess($complaint, $user);

        $attachment = ComplaintAttachment::where('complaint_id', $complaint->id)->find($attachmentId);

        if (!$attachment || !$attachment->storage_key) {
            return $this->errorResponse('NOT_FOUND', 'Lampiran bukti tidak ditemukan.', null, 404);
        }

        if (!Storage::disk('local')->exists($attachment->storage_key)) {
            return $this->errorResponse('NOT_FOUND', 'File bukti tidak ditemukan di penyimpanan server.', null, 404);
        }

        $path = Storage::disk('local')->path($attachment->storage_key);
        $mime = $attachment->mime_type ?: Storage::disk('local')->mimeType($attachment->storage_key) ?: 'image/jpeg';

        return response()->file($path, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /**
     * Store Admin: List complaints for their store(s) (FR-COM-03).
     */
    public function storeAdminIndex(Request $request): JsonResponse
    {
        $user = $request->user();

        // Find stores owned by user or where user is admin
        $storeIds = Store::where('owner_user_id', $user->id)->pluck('id');

        if ($storeIds->isEmpty() && !$user->hasRole('super_admin')) {
            return $this->errorResponse('FORBIDDEN', 'Anda tidak memiliki akses ke data komplain toko ini.', null, 403);
        }

        $query = Complaint::with(['order', 'customer', 'attachments']);

        if (!$user->hasRole('super_admin')) {
            $query->whereIn('store_id', $storeIds);
        }

        if ($request->has('status') && !empty($request->query('status'))) {
            $query->where('status', $request->query('status'));
        }

        if ($request->has('search') && !empty($request->query('search'))) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->whereHas('order', function ($oq) use ($search) {
                    $oq->where('order_number', 'ILIKE', "%{$search}%");
                })->orWhereHas('customer', function ($cq) use ($search) {
                    $cq->where('name', 'ILIKE', "%{$search}%");
                })->orWhere('description', 'ILIKE', "%{$search}%");
            });
        }

        $perPage = min((int) $request->query('per_page', 15), 50);
        $complaints = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->successResponse(
            data: collect($complaints->items())->map(fn ($c) => $this->formatComplaint($c)),
            meta: [
                'current_page' => $complaints->currentPage(),
                'last_page' => $complaints->lastPage(),
                'per_page' => $complaints->perPage(),
                'total' => $complaints->total(),
            ]
        );
    }

    /**
     * Store Admin: Update complaint status & resolution note (FR-COM-03).
     * REQUIREMENT: When closing complaint (RESOLVED / REJECTED), resolution_note is MANDATORY!
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $complaint = Complaint::with(['order', 'store', 'customer'])->find($id);

        if (!$complaint) {
            return $this->errorResponse('NOT_FOUND', 'Data komplain tidak ditemukan.', null, 404);
        }

        // Authorize store owner or super admin
        $isStoreOwner = $complaint->store && $complaint->store->owner_user_id === $user->id;
        if (!$isStoreOwner && !$user->hasRole('super_admin')) {
            return $this->errorResponse('FORBIDDEN', 'Hanya admin toko pengelola yang dapat memperbarui status komplain.', null, 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => ['required', 'string', 'in:OPEN,IN_INVESTIGATION,RESOLVED,REJECTED'],
            'resolution_note' => [
                'required_if:status,RESOLVED,REJECTED',
                'nullable',
                'string',
                'min:5',
                'max:2000',
            ],
        ], [
            'status.required' => 'Status komplain wajib ditentukan.',
            'status.in' => 'Status komplain tidak valid (pilihan: OPEN, IN_INVESTIGATION, RESOLVED, REJECTED).',
            'resolution_note.required_if' => 'Catatan penyelesaian (resolution_note) WAJIB diisi saat menutup komplain (status RESOLVED atau REJECTED).',
            'resolution_note.min' => 'Catatan penyelesaian minimal 5 karakter.',
            'resolution_note.max' => 'Catatan penyelesaian maksimal 2000 karakter.',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                'VALIDATION_ERROR',
                'Data pembaruan status komplain tidak valid.',
                $validator->errors()->toArray(),
                422
            );
        }

        $validated = $validator->validated();
        $newStatus = $validated['status'];
        $resolutionNote = $validated['resolution_note'] ?? null;

        // Extra check: ensure resolution_note is non-empty string when closing
        if (in_array($newStatus, ['RESOLVED', 'REJECTED']) && empty(trim((string) $resolutionNote))) {
            return $this->errorResponse(
                'RESOLUTION_NOTE_REQUIRED',
                'Catatan penyelesaian wajib diisi saat menyelesaikan atau menolak komplain.',
                ['resolution_note' => ['Catatan penyelesaian tidak boleh kosong.']],
                422
            );
        }

        $complaint->status = $newStatus;
        if ($resolutionNote !== null) {
            $complaint->resolution_note = trim($resolutionNote);
        }

        if (in_array($newStatus, ['RESOLVED', 'REJECTED'])) {
            $complaint->resolved_at = now();
            $complaint->resolved_by = $user->id;
        }

        $complaint->save();

        // In-app notification to customer
        $statusLabels = [
            'OPEN' => 'Dibuka Kembali',
            'IN_INVESTIGATION' => 'Sedang Diinvestigasi',
            'RESOLVED' => 'Telah Diselesaikan',
            'REJECTED' => 'Ditolak',
        ];
        $label = $statusLabels[$newStatus] ?? $newStatus;

        // Multi-channel notification to customer safely (FR-NOTIF)
        try {
            app(\App\Services\Notification\NotificationService::class)->notifyComplaintUpdated($complaint, $newStatus, $complaint->resolution_note);
        } catch (\Throwable $e) {
            // Ignore notification failure
        }


        return $this->successResponse(
            data: $this->formatComplaint($complaint->load(['order', 'store', 'customer', 'resolver', 'attachments'])),
            meta: ['message' => "Status komplain berhasil diperbarui menjadi {$label}."]
        );
    }

    /**
     * Verify user authorization to view a complaint.
     */
    protected function authorizeComplaintAccess(Complaint $complaint, $user): void
    {
        if ($user->hasRole('super_admin')) {
            return;
        }

        if ($complaint->customer_id === $user->id) {
            return;
        }

        if ($complaint->store && $complaint->store->owner_user_id === $user->id) {
            return;
        }

        abort(response()->json([
            'success' => false,
            'error' => [
                'code' => 'FORBIDDEN',
                'message' => 'Anda tidak memiliki hak akses untuk melihat data komplain ini.',
            ],
        ], 403));
    }

    /**
     * Format Complaint model into API structure.
     */
    protected function formatComplaint(Complaint $complaint): array
    {
        return [
            'id' => $complaint->id,
            'order_id' => $complaint->order_id,
            'order_number' => $complaint->order?->order_number,
            'customer_id' => $complaint->customer_id,
            'customer_name' => $complaint->customer?->name,
            'store_id' => $complaint->store_id,
            'store_name' => $complaint->store?->name,
            'category' => $complaint->category,
            'description' => $complaint->description,
            'status' => $complaint->status,
            'resolution_note' => $complaint->resolution_note,
            'resolved_at' => $complaint->resolved_at?->toIso8601String(),
            'resolved_by' => $complaint->resolved_by,
            'resolver_name' => $complaint->resolver?->name,
            'attachments' => $complaint->attachments->map(fn ($att) => [
                'id' => $att->id,
                'file_name' => $att->file_name,
                'file_size' => $att->file_size,
                'mime_type' => $att->mime_type,
                'url' => url("/api/v1/complaints/{$complaint->id}/attachments/{$att->id}"),
                'created_at' => $att->created_at?->toIso8601String(),
            ]),
            'created_at' => $complaint->created_at?->toIso8601String(),
            'updated_at' => $complaint->updated_at?->toIso8601String(),
        ];
    }
}
