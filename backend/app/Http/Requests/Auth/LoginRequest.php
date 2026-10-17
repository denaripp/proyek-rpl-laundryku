<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $mergeData = [];

        // Support phone_number or phone or email alias for destination
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

        if (!empty($mergeData)) {
            $this->merge($mergeData);
        }
    }

    public function rules(): array
    {
        $channel = $this->input('channel');

        $destinationRules = ['required', 'string'];
        if ($channel === 'email') {
            $destinationRules[] = 'email';
        } elseif ($channel === 'whatsapp') {
            $destinationRules[] = 'regex:/^(?:\+62|62|0)[0-9]{8,13}$/';
        }

        return [
            'channel' => ['required', 'in:email,whatsapp'],
            'destination' => $destinationRules,
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'channel.required' => 'Kanal login wajib diisi (email atau whatsapp).',
            'channel.in' => 'Kanal login hanya boleh email atau whatsapp.',
            'destination.required' => 'Email atau nomor WhatsApp wajib diisi.',
            'destination.email' => 'Format email tidak valid.',
            'destination.regex' => 'Format nomor WhatsApp Indonesia tidak valid (contoh: 081234567890).',
            'password.required' => 'Password wajib diisi.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => $validator->errors()->first(),
                'details' => $validator->errors(),
            ],
        ], 422));
    }
}
