<?php

namespace App\Http\Requests\Order;

use App\Models\ServicePrice;
use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && $user->hasPermissionTo('create_order');
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('pickup_address') && is_array($this->input('pickup_address'))) {
            $p = $this->input('pickup_address');
            $merge['pickup_address_detail'] = $p;
            $merge['pickup_address'] = $p['address_text'] ?? ($p['address'] ?? '');
            if (isset($p['latitude']) && !$this->has('pickup_latitude')) {
                $merge['pickup_latitude'] = $p['latitude'];
            }
            if (isset($p['longitude']) && !$this->has('pickup_longitude')) {
                $merge['pickup_longitude'] = $p['longitude'];
            }
        }

        if ($this->has('delivery_address') && is_array($this->input('delivery_address'))) {
            $d = $this->input('delivery_address');
            $merge['delivery_address_detail'] = $d;
            $merge['delivery_address'] = $d['address_text'] ?? ($d['address'] ?? '');
            if (isset($d['latitude']) && !$this->has('delivery_latitude')) {
                $merge['delivery_latitude'] = $d['latitude'];
            }
            if (isset($d['longitude']) && !$this->has('delivery_longitude')) {
                $merge['delivery_longitude'] = $d['longitude'];
            }
        } elseif (!$this->has('delivery_address') && isset($merge['pickup_address'])) {
            $merge['delivery_address'] = $merge['pickup_address'];
            $merge['delivery_latitude'] = $merge['pickup_latitude'] ?? null;
            $merge['delivery_longitude'] = $merge['pickup_longitude'] ?? null;
            $merge['delivery_address_detail'] = $merge['pickup_address_detail'] ?? null;
        }

        if (!$this->has('estimated_weight')) {
            $items = $this->input('items', []);
            $weight = 0;
            if (is_array($items)) {
                foreach ($items as $it) {
                    $weight += (float) ($it['quantity'] ?? 1);
                }
            }
            $merge['estimated_weight'] = max(0.1, $weight);
        }

        // Server-side calculation of estimated price to prevent fee manipulation
        $items = $this->input('items', []);
        $storeId = $this->input('store_id');
        $subtotalServices = 0;
        if (is_array($items) && $storeId) {
            foreach ($items as $it) {
                $qty = (float) ($it['quantity'] ?? 1);
                $sp = ServicePrice::where('store_id', $storeId)
                    ->where('is_active', true)
                    ->where(function ($q) use ($it) {
                        if (isset($it['service_id'])) {
                            $q->where('id', $it['service_id'])->orWhere('service_id', $it['service_id']);
                        }
                    })
                    ->first();
                if ($sp) {
                    $raw = $qty * (float) $sp->price_per_unit;
                    $min = (float) ($sp->minimum_charge ?? 0);
                    $subtotalServices += ($min > 0 && $raw < $min) ? $min : $raw;
                }
            }
        }
        $platformFee = $subtotalServices > 0 ? 1000 : 0;
        $calculatedEstimatedPrice = $subtotalServices > 0 ? ($subtotalServices + $platformFee) : 1000;

        if (!$this->has('estimated_price') || (float) $this->input('estimated_price') < $calculatedEstimatedPrice) {
            $merge['estimated_price'] = $calculatedEstimatedPrice;
        }

        if (!empty($merge)) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        $storeId = $this->input('store_id');

        return [
            'store_id' => 'required|integer|exists:stores,id',
            'estimated_weight' => 'required|numeric|min:0.1|max:1000',
            'estimated_price' => 'required|numeric|min:1000',
            'items' => 'required|array|min:1',
            'items.*.service_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) use ($storeId) {
                    if (!$storeId) {
                        return;
                    }
                    $exists = ServicePrice::where('store_id', $storeId)
                        ->where('is_active', true)
                        ->where(function ($q) use ($value) {
                            $q->where('id', $value)
                              ->orWhere('service_id', $value);
                        })
                        ->exists();

                    if (!$exists) {
                        $fail("The selected {$attribute} is invalid.");
                    }
                },
            ],
            'items.*.quantity' => 'required|numeric|min:0.1',

            'pickup_address' => 'required|string|max:500',
            'pickup_latitude' => 'required|numeric|between:-90,90',
            'pickup_longitude' => 'required|numeric|between:-180,180',
            'pickup_address_detail' => 'nullable|array',
            'delivery_address' => 'required|string|max:500',
            'delivery_latitude' => 'required|numeric|between:-90,90',
            'delivery_longitude' => 'required|numeric|between:-180,180',
            'delivery_address_detail' => 'nullable|array',

            'customer_notes' => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'store_id.exists' => 'Selected store does not exist.',
            'items.min' => 'Order must contain at least one item.',
            'pickup_latitude.between' => 'Invalid pickup latitude.',
            'pickup_longitude.between' => 'Invalid pickup longitude.',
        ];
    }
}
