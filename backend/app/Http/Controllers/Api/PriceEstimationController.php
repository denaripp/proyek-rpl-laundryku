<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StoreService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PriceEstimationController extends Controller
{
    use ApiResponse;

    /**
     * Calculate price estimation for laundry order items (FR-PRICE-01, FR-PRICE-03).
     * Calculates item subtotals, applies minimum_charge, and aggregates duration & total.
     */
    public function estimate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'store_id' => ['required', 'uuid', 'exists:stores,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.service_id' => ['required', 'uuid', 'exists:store_services,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:1000.0'],
        ], [
            'store_id.required' => 'ID toko wajib disertakan.',
            'store_id.exists' => 'Toko tidak ditemukan.',
            'items.required' => 'Daftar item pesanan wajib disertakan minimal 1 item.',
            'items.*.service_id.required' => 'ID layanan pada setiap item wajib disertakan.',
            'items.*.quantity.required' => 'Estimasi kuantitas/berat cucian wajib diisi.',
            'items.*.quantity.min' => 'Kuantitas minimal adalah 0.01.',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                'VALIDATION_ERROR',
                'Data permintaan estimasi harga tidak valid.',
                $validator->errors()->toArray(),
                422
            );
        }

        $storeId = $request->input('store_id');
        $itemsInput = $request->input('items');

        $store = Store::where('id', $storeId)
            ->where('approval_status', 'approved')
            ->where('operational_status', 'active')
            ->first();

        if (!$store) {
            return $this->errorResponse(
                'STORE_NOT_AVAILABLE',
                'Toko tidak tersedia atau sedang tidak aktif.',
                null,
                404
            );
        }

        $itemResults = [];
        $totalRawSubtotal = 0;
        $totalEstimatedPrice = 0;
        $maxDurationHours = 0;

        foreach ($itemsInput as $item) {
            $serviceId = $item['service_id'];
            $quantity = (float) $item['quantity'];

            $service = StoreService::with('category:id,name,slug')
                ->where('id', $serviceId)
                ->where('store_id', $store->id)
                ->first();

            if (!$service) {
                return $this->errorResponse(
                    'SERVICE_NOT_FOUND_IN_STORE',
                    "Layanan dengan ID {$serviceId} tidak terdaftar pada toko ini.",
                    ['service_id' => $serviceId],
                    422
                );
            }

            if (!$service->is_active) {
                return $this->errorResponse(
                    'SERVICE_INACTIVE',
                    "Layanan '{$service->name}' sedang tidak aktif dan tidak dapat dipesan.",
                    ['service_id' => $serviceId],
                    422
                );
            }

            /**
             * Structure of Price Calculation (FR-PRICE-01, FR-PRICE-03):
             * 1. Qty processing based on unit:
             *    - 'item': quantized to integer (or float if specified)
             *    - 'kg': exact float weight
             * 2. Subtotal = Qty * PricePerUnit
             * 3. TODO: Aturan pembulatan berat kg (misal pembulatan ke atas per 0.5 kg atau per 1.0 kg)
             *    belum dispesifikasikan di PRD 8.8 (FR-PRICE-03). Saat ini perhitungan menggunakan nilai desimal
             *    eksak dikalikan tarif per kg.
             * 4. Minimum charge check:
             *    If Subtotal < MinimumCharge -> ChargedAmount = MinimumCharge, minimum_charge_applied = true.
             *    Else -> ChargedAmount = Subtotal, minimum_charge_applied = false.
             */
            $pricePerUnit = (int) $service->price_per_unit;
            $minimumCharge = (int) $service->minimum_charge;

            // TODO: Aturan pembulatan desimal berat kg (FR-PRICE-03) belum didefinisikan di PRD.
            // Saat ini menggunakan nilai perkalian nominal eksak yang dibulatkan ke integer Rupiah.
            $rawSubtotal = (int) round($quantity * $pricePerUnit);

            $minChargeApplied = false;
            $chargedAmount = $rawSubtotal;

            if ($minimumCharge > 0 && $rawSubtotal < $minimumCharge) {
                $chargedAmount = $minimumCharge;
                $minChargeApplied = true;
            }

            $totalRawSubtotal += $rawSubtotal;
            $totalEstimatedPrice += $chargedAmount;
            $maxDurationHours = max($maxDurationHours, (int) $service->estimated_duration_hours);

            $itemResults[] = [
                'service_id' => $service->id,
                'service_name' => $service->name,
                'category_name' => $service->category?->name,
                'pricing_unit' => $service->pricing_unit,
                'price_per_unit' => $pricePerUnit,
                'quantity' => $quantity,
                'subtotal' => $rawSubtotal,
                'minimum_charge' => $minimumCharge,
                'minimum_charge_applied' => $minChargeApplied,
                'charged_amount' => $chargedAmount,
                'estimated_duration_hours' => (int) $service->estimated_duration_hours,
            ];
        }

        return $this->successResponse(
            data: [
                'store' => [
                    'id' => $store->id,
                    'name' => $store->name,
                    'service_radius_km' => (float) $store->service_radius_km,
                ],
                'items' => $itemResults,
                'summary' => [
                    'total_items' => count($itemResults),
                    'total_raw_subtotal' => $totalRawSubtotal,
                    'total_estimated_price' => $totalEstimatedPrice,
                    'max_estimated_duration_hours' => $maxDurationHours,
                    'currency' => 'IDR',
                    'notes' => 'Estimasi harga dihitung sebelum penimbangan fisik. Harga final dapat disetujui atau dibatalkan saat penimbangan aktual di toko (US-CUS-07).',
                ],
            ],
            meta: [
                'calculated_at' => now()->toIso8601String(),
            ]
        );
    }
}
