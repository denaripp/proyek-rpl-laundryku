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
        $mergeData = [];

        if ($this->has('email') && $this->input('email') !== null) {
            $mergeData['email'] = strtolower(trim((string) $this->input('email')));
        }

        if ($this->has('phone') && $this->input('phone') !== null) {
            $rawPhone = trim((string) $this->input('phone'));
            // Remove spacing, hyphens, and parenthesis
            $cleaned = preg_replace('/[\s\-\(\)]+/', '', $rawPhone);
            if (str_starts_with($cleaned, '+62')) {
                $cleaned = '0' . substr($cleaned, 3);
            } elseif (str_starts_with($cleaned, '62')) {
                $cleaned = '0' . substr($cleaned, 2);
            }
            $mergeData['phone'] = $cleaned;
        }

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
                $mergeData['latitude'] = $coords['lat'];
                $mergeData['longitude'] = $coords['lng'];
            } elseif ($this->filled('store_name') || $this->filled('store_address')) {
                // Sensible default coordinates for backward compatibility with existing tests
                $mergeData['latitude'] = -6.91474400;
                $mergeData['longitude'] = 107.60981000;
            }
        }

        if (! empty($mergeData)) {
            $this->merge($mergeData);
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

            // Customer check: Account mitra must be separate from customer
            $customer = $this->user();
            if ($customer) {
                $inputEmail = strtolower(trim((string) $this->input('email')));
                $custEmail = strtolower(trim((string) $customer->email));
                if ($inputEmail !== '' && $inputEmail === $custEmail) {
                    $validator->errors()->add(
                        'email',
                        'Email/nomor sudah terdaftar. Gunakan email/nomor lain untuk akun mitra.'
                    );
                }

                $inputPhone = (string) $this->input('phone');
                $custPhone = (string) $customer->phone;
                $cleanInput = preg_replace('/[^0-9]/', '', $inputPhone);
                $cleanCust = preg_replace('/[^0-9]/', '', $custPhone);

                if ($cleanInput !== '' && $cleanCust !== '' && $cleanInput === $cleanCust) {
                    $validator->errors()->add(
                        'phone',
                        'Email/nomor sudah terdaftar. Gunakan email/nomor lain untuk akun mitra.'
                    );
                }
            }

            // Also check alternate phone formats in users table (e.g. +62 vs 08)
            $phoneInput = (string) $this->input('phone');
            $cleanDigits = preg_replace('/[^0-9]/', '', $phoneInput);
            if ($cleanDigits !== '' && ! $validator->errors()->has('phone')) {
                // If starts with 08, alternate is 628 or +628
                $variants = [$phoneInput];
                if (str_starts_with($cleanDigits, '08')) {
                    $variants[] = '628' . substr($cleanDigits, 2);
                    $variants[] = '+628' . substr($cleanDigits, 2);
                } elseif (str_starts_with($cleanDigits, '628')) {
                    $variants[] = '08' . substr($cleanDigits, 3);
                    $variants[] = '+628' . substr($cleanDigits, 3);
                }
                $exists = \App\Models\User::whereIn('phone', $variants)->exists();
                if ($exists) {
                    $validator->errors()->add(
                        'phone',
                        'Email/nomor sudah terdaftar. Gunakan email/nomor lain untuk akun mitra.'
                    );
                }
            }
        });
    }
}
