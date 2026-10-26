<?php

namespace App\Http\Requests\Mitra;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStoreProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'phone' => ['required', 'string', 'max:20', 'regex:/^[+0-9\-\s()]+$/'],
            'address' => 'required|string|max:500',
            'description' => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama gerai wajib diisi.',
            'phone.required' => 'Nomor telepon gerai wajib diisi.',
            'phone.regex' => 'Format nomor telepon gerai tidak valid.',
            'address.required' => 'Alamat gerai wajib diisi.',
        ];
    }
}
