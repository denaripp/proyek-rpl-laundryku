<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;

class SearchStoresRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('lat') && !$this->has('latitude')) {
            $merge['latitude'] = $this->input('lat');
        }
        if ($this->has('lng') && !$this->has('longitude')) {
            $merge['longitude'] = $this->input('lng');
        }
        if ($this->has('search') && !$this->has('q')) {
            $merge['q'] = $this->input('search');
        }
        if (!empty($merge)) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        return [
            'q' => 'nullable|string|max:100',
            'search' => 'nullable|string|max:100',
            'service_id' => 'nullable|integer|exists:services,id',
            'min_rating' => 'nullable|numeric|min:0|max:5',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'radius_km' => 'nullable|numeric|min:0.1|max:50',
            'sort' => 'nullable|string|in:distance,rating,newest',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:50',
        ];
    }
}
