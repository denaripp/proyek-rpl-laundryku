<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SendOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'uuid'],
            'channel' => ['required_without:user_id', 'in:email,whatsapp'],
            'destination' => ['required_without:user_id', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'channel.in' => 'Kanal pengiriman hanya boleh email atau whatsapp.',
            'destination.required_without' => 'Tujuan pengiriman (email atau nomor WhatsApp) wajib diisi jika user_id tidak disertakan.',
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
