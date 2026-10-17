<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is handled in the controller via Gate::authorize('update', $store).
        // Returning true here is safe because the controller re-checks.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'phone' => 'sometimes|required|string|max:20|regex:/^[+0-9\-\s()]+$/',
            'address' => 'sometimes|required|string|max:500',
            'latitude' => 'sometimes|required|numeric|between:-90,90',
            'longitude' => 'sometimes|required|numeric|between:-180,180',
            'service_radius_km' => 'nullable|numeric|min:0.1|max:100',
        ];
    }
}
