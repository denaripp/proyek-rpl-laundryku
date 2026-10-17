<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ServiceCategory;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AdminCategoryController extends Controller
{
    use ApiResponse;

    /**
     * List all service categories.
     * FR-ADMIN-06, US-ADMIN-05
     */
    public function index(Request $request): JsonResponse
    {
        $query = ServiceCategory::withCount('services');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('q')) {
            $search = '%' . $request->query('q') . '%';
            $query->where('name', 'LIKE', $search);
        }

        $categories = $query->orderBy('name', 'asc')->get();

        $data = $categories->map(fn ($cat) => [
            'id' => $cat->id,
            'name' => $cat->name,
            'slug' => $cat->slug,
            'status' => $cat->status,
            'services_count' => $cat->services_count,
            'created_at' => $cat->created_at?->toIso8601String(),
            'updated_at' => $cat->updated_at?->toIso8601String(),
        ]);

        return $this->successResponse(
            data: $data
        );
    }

    /**
     * Create new service category.
     * FR-ADMIN-06, US-ADMIN-05
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100', 'unique:service_categories,name'],
            'slug' => ['nullable', 'string', 'max:100', 'unique:service_categories,slug'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ], [
            'name.required' => 'Nama kategori layanan wajib diisi.',
            'name.unique' => 'Kategori layanan dengan nama ini sudah ada.',
            'slug.unique' => 'Slug kategori layanan sudah digunakan.',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', 'Input tidak valid.', $validator->errors()->toArray(), 422);
        }

        $validated = $validator->validated();
        $slug = !empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($validated['name']);
        
        // Ensure slug uniqueness
        $baseSlug = $slug;
        $count = 1;
        while (ServiceCategory::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$count}";
            $count++;
        }

        $category = ServiceCategory::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'status' => $validated['status'] ?? 'active',
        ]);

        // Record Audit Log (FR-ADMIN-10, US-ADMIN-08)
        AuditLog::record(
            actor: $user,
            action: 'CREATE_SERVICE_CATEGORY',
            entityType: 'service_categories',
            entityId: $category->id,
            metadata: [
                'name' => $category->name,
                'slug' => $category->slug,
                'status' => $category->status,
            ],
            ip: $request->ip()
        );

        return $this->successResponse(
            data: [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'status' => $category->status,
                'services_count' => 0,
                'created_at' => $category->created_at?->toIso8601String(),
            ],
            meta: ['message' => "Kategori '{$category->name}' berhasil dibuat."],
            status: 201
        );
    }

    /**
     * Show service category.
     */
    public function show(string $id): JsonResponse
    {
        $category = ServiceCategory::withCount('services')->find($id);

        if (!$category) {
            return $this->errorResponse('NOT_FOUND', 'Kategori layanan tidak ditemukan.', null, 404);
        }

        return $this->successResponse(
            data: [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'status' => $category->status,
                'services_count' => $category->services_count,
                'created_at' => $category->created_at?->toIso8601String(),
                'updated_at' => $category->updated_at?->toIso8601String(),
            ]
        );
    }

    /**
     * Update service category.
     * FR-ADMIN-06, US-ADMIN-05
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $category = ServiceCategory::find($id);

        if (!$category) {
            return $this->errorResponse('NOT_FOUND', 'Kategori layanan tidak ditemukan.', null, 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100', 'unique:service_categories,name,' . $id],
            'slug' => ['nullable', 'string', 'max:100', 'unique:service_categories,slug,' . $id],
            'status' => ['required', 'string', 'in:active,inactive'],
        ], [
            'name.required' => 'Nama kategori layanan wajib diisi.',
            'name.unique' => 'Kategori layanan dengan nama ini sudah ada.',
            'status.required' => 'Status kategori layanan wajib dipilih.',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', 'Input tidak valid.', $validator->errors()->toArray(), 422);
        }

        $validated = $validator->validated();
        $oldData = $category->toArray();

        $slug = !empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($validated['name']);

        $category->update([
            'name' => $validated['name'],
            'slug' => $slug,
            'status' => $validated['status'],
        ]);

        // Record Audit Log (FR-ADMIN-10, US-ADMIN-08)
        AuditLog::record(
            actor: $user,
            action: 'UPDATE_SERVICE_CATEGORY',
            entityType: 'service_categories',
            entityId: $category->id,
            metadata: [
                'old' => $oldData,
                'new' => $category->fresh()->toArray(),
            ],
            ip: $request->ip()
        );

        return $this->successResponse(
            data: [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'status' => $category->status,
                'created_at' => $category->created_at?->toIso8601String(),
                'updated_at' => $category->updated_at?->toIso8601String(),
            ],
            meta: ['message' => "Kategori '{$category->name}' berhasil diperbarui."]
        );
    }

    /**
     * Delete or toggle deactivate service category.
     * FR-ADMIN-06, US-ADMIN-05
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $category = ServiceCategory::withCount('services')->find($id);

        if (!$category) {
            return $this->errorResponse('NOT_FOUND', 'Kategori layanan tidak ditemukan.', null, 404);
        }

        $oldName = $category->name;

        // If category is linked to existing services, deactivate instead of hard delete
        if ($category->services_count > 0) {
            $category->update(['status' => 'inactive']);

            AuditLog::record(
                actor: $user,
                action: 'DEACTIVATE_SERVICE_CATEGORY',
                entityType: 'service_categories',
                entityId: $category->id,
                metadata: [
                    'name' => $oldName,
                    'reason' => 'Category in use by store services; deactivated instead of deleted.',
                ],
                ip: $request->ip()
            );

            return $this->successResponse(
                data: ['id' => $category->id, 'status' => 'inactive'],
                meta: ['message' => "Kategori '{$oldName}' dinonaktifkan karena masih digunakan oleh layanan toko."]
            );
        }

        $category->delete();

        AuditLog::record(
            actor: $user,
            action: 'DELETE_SERVICE_CATEGORY',
            entityType: 'service_categories',
            entityId: $id,
            metadata: ['name' => $oldName],
            ip: $request->ip()
        );

        return $this->successResponse(
            data: ['id' => $id],
            meta: ['message' => "Kategori '{$oldName}' berhasil dihapus."]
        );
    }
}
