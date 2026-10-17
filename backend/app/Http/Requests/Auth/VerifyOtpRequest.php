<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'uuid'],
            'channel' => ['nullable', 'in:email,whatsapp'],
            'destination' => ['nullable', 'string'],
            'otp' => ['required_without:otp_code', 'nullable', 'string', 'digits:6'],
            'otp_code' => ['required_without:otp', 'nullable', 'string', 'digits:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'otp.required_without' => 'Kode OTP wajib diisi.',
            'otp.digits' => 'Kode OTP harus terdiri dari 6 digit angka.',
            'otp_code.required_without' => 'Kode OTP wajib diisi.',
            'otp_code.digits' => 'Kode OTP harus terdiri dari 6 digit angka.',
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
