<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && $user->hasPermissionTo('create_order');
    }

    public function rules(): array
    {
        return [
            'store_id' => 'required|integer|exists:stores,id',
            'estimated_weight' => 'required|numeric|min:0.1|max:1000',
            'estimated_price' => 'required|numeric|min:1000',
            'items' => 'required|array|min:1',
            'items.*.service_id' => 'required|integer|exists:services,id',
            'items.*.quantity' => 'required|numeric|min:0.1',

            'pickup_address' => 'required|string|max:500',
            'pickup_latitude' => 'required|numeric|between:-90,90',
            'pickup_longitude' => 'required|numeric|between:-180,180',
            'delivery_address' => 'required|string|max:500',
            'delivery_latitude' => 'required|numeric|between:-90,90',
            'delivery_longitude' => 'required|numeric|between:-180,180',

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
