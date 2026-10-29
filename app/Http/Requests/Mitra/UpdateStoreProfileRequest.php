<?php

namespace App\Http\Requests\Mitra;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStoreProfileRequest extends FormRequest
{
    public const VALID_DAYS = ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu'];

    public const DAY_LABELS = [
        'senin' => 'Senin',
        'selasa' => 'Selasa',
        'rabu' => 'Rabu',
        'kamis' => 'Kamis',
        'jumat' => 'Jumat',
        'sabtu' => 'Sabtu',
        'minggu' => 'Minggu',
    ];

    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        // Staf toko tidak memiliki izin untuk mengubah profil gerai (hanya pemilik toko)
        $isOwner = \App\Models\Store::where('owner_user_id', $user->id)->exists();
        $isStaff = \App\Models\StoreStaff::where('user_id', $user->id)->where('is_active', true)->exists();

        if ($isStaff && ! $isOwner) {
            return false;
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        // If latitude / longitude omitted entirely (e.g. partial whitelisting tests), populate from current store
        if (! $this->has('latitude') && ! $this->has('longitude')) {
            $user = $this->user();
            if ($user) {
                $store = \App\Models\Store::where('owner_user_id', $user->id)->first()
                    ?? (method_exists($user, 'storeAdminStore') ? $user->storeAdminStore() : null);
                if ($store && $store->latitude !== null && $store->longitude !== null) {
                    $this->merge([
                        'latitude' => (float) $store->latitude,
                        'longitude' => (float) $store->longitude,
                    ]);
                }
            }
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'phone' => ['required', 'string', 'max:20', 'regex:/^[+0-9\-\s()]+$/'],
            'address' => 'required|string|max:500',
            'description' => 'nullable|string|max:2000',
            'city' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'service_radius_km' => [
                'nullable',
                'numeric',
                'min:0.5',
                'max:50',
                'regex:/^\d+(\.\d{1,2})?$/',
            ],
            'operational_hours' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama gerai wajib diisi.',
            'phone.required' => 'Nomor telepon gerai wajib diisi.',
            'phone.regex' => 'Format nomor telepon gerai tidak valid.',
            'address.required' => 'Alamat gerai wajib diisi.',
            'latitude.required' => 'Koordinat lokasi gerai wajib diisi.',
            'longitude.required' => 'Koordinat lokasi gerai wajib diisi.',
            'service_radius_km.numeric' => 'Radius layanan harus berupa angka.',
            'service_radius_km.min' => 'Radius jangkauan layanan minimal 0,5 km.',
            'service_radius_km.max' => 'Radius jangkauan layanan maksimal 50 km.',
            'service_radius_km.regex' => 'Radius layanan maksimal memiliki dua angka di belakang koma.',
            'latitude.between' => 'Koordinat latitude harus antara -90 dan 90.',
            'longitude.between' => 'Koordinat longitude harus antara -180 dan 180.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $lat = $this->input('latitude');
            $lng = $this->input('longitude');

            if ($lat === null || $lng === null || $lat === '' || $lng === '') {
                $validator->errors()->add(
                    'latitude',
                    'Koordinat lokasi gerai wajib diisi dan ditentukan pada peta.'
                );
            } elseif (is_numeric($lat) && is_numeric($lng)) {
                $latVal = (float) $lat;
                $lngVal = (float) $lng;

                if ($latVal === 0.0 && $lngVal === 0.0) {
                    $validator->errors()->add(
                        'latitude',
                        'Koordinat lokasi tidak boleh titik default (0, 0). Tentukan titik lokasi gerai yang valid.'
                    );
                } elseif (abs($latVal - (-6.2088)) < 0.0005 && abs($lngVal - 106.8456) < 0.0005) {
                    $validator->errors()->add(
                        'latitude',
                        'Koordinat lokasi gerai wajib ditentukan dan tidak boleh menggunakan titik default (-6.2088, 106.8456).'
                    );
                }
            }

            $hours = $this->input('operational_hours');
            if ($hours === null) {
                return;
            }

            if (! is_array($hours)) {
                $validator->errors()->add('operational_hours', 'Format jam operasional harus berupa data terstruktur.');
                return;
            }

            // Validasi kelengkapan 7 hari
            foreach (self::VALID_DAYS as $day) {
                $dayLabel = self::DAY_LABELS[$day];
                if (! array_key_exists($day, $hours) || ! is_array($hours[$day])) {
                    $validator->errors()->add("operational_hours.{$day}", "Pengaturan jam operasional untuk hari {$dayLabel} wajib diisi.");
                    continue;
                }

                $dayConfig = $hours[$day];
                $isClosed = filter_var($dayConfig['is_closed'] ?? false, FILTER_VALIDATE_BOOLEAN);

                if (! $isClosed) {
                    $open = trim((string) ($dayConfig['open'] ?? ''));
                    $close = trim((string) ($dayConfig['close'] ?? ''));

                    $timePattern = '/^([01]\d|2[0-3]):[0-5]\d$/';

                    if (! preg_match($timePattern, $open)) {
                        $validator->errors()->add("operational_hours.{$day}.open", "Format jam buka hari {$dayLabel} harus HH:MM (contoh: 08:00).");
                    }

                    if (! preg_match($timePattern, $close)) {
                        $validator->errors()->add("operational_hours.{$day}.close", "Format jam tutup hari {$dayLabel} harus HH:MM (contoh: 20:00).");
                    }

                    if (preg_match($timePattern, $open) && preg_match($timePattern, $close)) {
                        if ($close <= $open) {
                            $validator->errors()->add("operational_hours.{$day}.close", "Jam tutup harus lebih besar dari jam buka pada hari {$dayLabel}.");
                        }
                    }
                }
            }
        });
    }
}
