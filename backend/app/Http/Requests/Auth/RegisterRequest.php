<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $mergeData = [];

        // Support full_name alias for name
        if (!$this->has('name') && $this->has('full_name')) {
            $mergeData['name'] = $this->input('full_name');
        } elseif ($this->has('name') && !$this->has('full_name')) {
            $mergeData['full_name'] = $this->input('name');
        }

        // Support phone_number or phone alias for destination
        if (!$this->has('destination')) {
            if ($this->has('phone_number')) {
                $mergeData['destination'] = $this->input('phone_number');
                if (!$this->has('channel')) {
                    $mergeData['channel'] = 'whatsapp';
                }
            } elseif ($this->has('phone')) {
                $mergeData['destination'] = $this->input('phone');
                if (!$this->has('channel')) {
                    $mergeData['channel'] = 'whatsapp';
                }
            } elseif ($this->has('email')) {
                $mergeData['destination'] = $this->input('email');
                if (!$this->has('channel')) {
                    $mergeData['channel'] = 'email';
                }
            }
        }

        if ($this->has('destination') && !$this->has('phone_number') && $this->input('channel') === 'whatsapp') {
            $mergeData['phone_number'] = $this->input('destination');
        }

        if (!empty($mergeData)) {
            $this->merge($mergeData);
        }
    }

    public function rules(): array
    {
        $channel = $this->input('channel', 'whatsapp');

        $destinationRules = ['required', 'string'];
        if ($channel === 'email') {
            $destinationRules[] = 'email';
            $destinationRules[] = 'max:255';
        } else {
            // Indonesian phone regex (08..., 62..., +62...)
            $destinationRules[] = 'regex:/^(?:\+62|62|0)[0-9]{8,13}$/';
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'full_name' => ['sometimes', 'string', 'max:255'],
            'channel' => ['required', 'in:email,whatsapp'],
            'destination' => $destinationRules,
            'phone_number' => ['sometimes', 'regex:/^(?:\+62|62|0)[0-9]{8,13}$/'],
            'password' => ['required', 'string', 'confirmed', new \App\Rules\StrongPassword()],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama lengkap wajib diisi.',
            'full_name.required' => 'Nama lengkap wajib diisi.',
            'channel.required' => 'Pilih channel: whatsapp atau email.',
            'channel.in' => 'Kanal registrasi hanya boleh email atau whatsapp.',
            'destination.required' => 'Email atau nomor WhatsApp wajib diisi.',
            'destination.email' => 'Format email tidak valid.',
            'destination.regex' => 'Format nomor WhatsApp harus 08xxx atau 628xxx.',
            'phone_number.regex' => 'Format nomor WhatsApp harus 08xxx atau 628xxx.',
            'phone_number.unique' => 'Nomor WhatsApp sudah terdaftar.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal terdiri dari 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validasi gagal: ' . $validator->errors()->first(),
            'errors' => $validator->errors(),
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => $validator->errors()->first(),
                'details' => $validator->errors(),
            ],
        ], 422));
    }
}
