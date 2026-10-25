<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class CreateAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('address_text') && !$this->has('address')) {
            $merge['address'] = $this->input('address_text');
        }
        if ($this->has('lat') && !$this->has('latitude')) {
            $merge['latitude'] = $this->input('lat');
        }
        if ($this->has('lng') && !$this->has('longitude')) {
            $merge['longitude'] = $this->input('lng');
        }
        if (!empty($merge)) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        return [
            'label' => 'required|string|max:100',
            'address' => 'required|string|max:500',
            'address_text' => 'nullable|string|max:500',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'is_default' => 'nullable|boolean',
            'recipient_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'rt_rw' => 'nullable|string|max:20',
            'kelurahan' => 'nullable|string|max:100',
            'kecamatan' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:10',
            'address_note' => 'nullable|string|max:500',
        ];
    }
}
