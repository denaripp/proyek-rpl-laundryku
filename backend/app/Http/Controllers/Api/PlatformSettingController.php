<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformSettingController extends Controller
{
    use ApiResponse;
    /**
     * Get platform commission configuration (FR-PAY-10, US-ADMIN-04).
     */
    public function getCommission(Request $request): JsonResponse
    {
        $setting = PlatformSetting::where('key', 'platform_commission_flat')->first();
        $amount = $setting && isset($setting->value_json['amount']) ? (int) $setting->value_json['amount'] : 1000;

        return $this->successResponse(
            data: [
                'key' => 'platform_commission_flat',
                'amount' => $amount,
                'description' => $setting?->description ?? 'Besaran komisi platform flat per order selesai (Rupiah)',
                'updated_at' => $setting?->updated_at?->toIso8601String(),
            ]
        );
    }

    /**
     * Update platform commission configuration (Super Admin only) (US-ADMIN-04).
     */
    public function updateCommission(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasRole('super_admin')) {
            return $this->errorResponse('FORBIDDEN', 'Hanya Super Admin yang dapat mengubah pengaturan komisi platform.', null, 403);
        }

        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:0', 'max:100000'],
            'description' => ['nullable', 'string', 'max:255'],
        ], [
            'amount.required' => 'Nominal komisi platform wajib diisi.',
            'amount.min' => 'Nominal komisi platform tidak boleh bernilai negatif.',
        ]);

        $existingSetting = PlatformSetting::where('key', 'platform_commission_flat')->first();
        $oldAmount = $existingSetting && isset($existingSetting->value_json['amount']) ? (int) $existingSetting->value_json['amount'] : 1000;

        $setting = PlatformSetting::updateOrCreate(
            ['key' => 'platform_commission_flat'],
            [
                'value_json' => ['amount' => (int) $validated['amount']],
                'description' => $validated['description'] ?? 'Besaran komisi platform flat per order selesai (Rupiah)',
                'updated_by' => $user->id,
            ]
        );

        // Record Audit Log (FR-ADMIN-10, US-ADMIN-08)
        \App\Models\AuditLog::record(
            actor: $user,
            action: 'UPDATE_COMMISSION',
            entityType: 'platform_settings',
            entityId: 'platform_commission_flat',
            metadata: [
                'old_amount' => $oldAmount,
                'new_amount' => (int) $validated['amount'],
                'description' => $setting->description,
            ],
            ip: $request->ip()
        );

        return $this->successResponse(
            data: [
                'key' => $setting->key,
                'amount' => (int) $setting->value_json['amount'],
                'description' => $setting->description,
                'updated_at' => $setting->updated_at?->toIso8601String(),
            ],
            meta: ['message' => 'Konfigurasi komisi platform berhasil diperbarui.']
        );
    }
}
