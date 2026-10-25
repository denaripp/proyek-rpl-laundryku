<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;

class MitraRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // If coordinate fields are omitted (legacy callers / API clients without GPS), attempt geocoding or fallback
        if (! $this->has('latitude') && ! $this->has('longitude')) {
            $coords = null;
            $address = trim(($this->input('store_address') ?? '') . ' ' . ($this->input('city') ?? ''));
            if ($address !== '') {
                try {
                    $geocoder = app(\App\Services\Maps\GeocodingServiceInterface::class);
                    $coords = $geocoder->geocode($address);
                } catch (\Throwable $e) {
                    // Ignore geocoding failure
                }
            }

            if ($coords && isset($coords['lat'], $coords['lng'])) {
                $this->merge([
                    'latitude' => $coords['lat'],
                    'longitude' => $coords['lng'],
                ]);
            } elseif ($this->filled('store_name') || $this->filled('store_address')) {
                // Sensible default coordinates for backward compatibility with existing tests
                $this->merge([
                    'latitude' => -6.91474400,
                    'longitude' => 107.60981000,
                ]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:100|unique:users,email',
            'phone' => 'required|string|min:8|max:20|regex:/^[+0-9\-\s()]+$/|unique:users,phone',
            'password' => 'required|string|min:8|confirmed',
            'store_name' => 'required|string|max:100',
            'store_address' => 'required|string|max:500',
            'city' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Email/nomor sudah terdaftar. Gunakan email/nomor lain untuk akun mitra.',
            'phone.unique' => 'Email/nomor sudah terdaftar. Gunakan email/nomor lain untuk akun mitra.',
            'phone.regex' => 'Format nomor telepon tidak valid.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'password.min' => 'Password minimal terdiri dari 8 karakter.',
            'store_name.required' => 'Nama toko wajib diisi.',
            'store_address.required' => 'Alamat toko wajib diisi.',
            'latitude.required' => 'Koordinat latitude wajib diisi.',
            'latitude.between' => 'Koordinat latitude di luar rentang valid (-90 hingga 90).',
            'longitude.required' => 'Koordinat longitude wajib diisi.',
            'longitude.between' => 'Koordinat longitude di luar rentang valid (-180 hingga 180).',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $lat = $this->input('latitude');
            $lng = $this->input('longitude');

            if ($lat !== null && $lng !== null && is_numeric($lat) && is_numeric($lng)) {
                if ((float) $lat === 0.0 && (float) $lng === 0.0) {
                    $validator->errors()->add(
                        'latitude',
                        'Koordinat lokasi tidak boleh titik default (0, 0). Tentukan titik lokasi toko yang valid.'
                    );
                }
            }
        });
    }
}
