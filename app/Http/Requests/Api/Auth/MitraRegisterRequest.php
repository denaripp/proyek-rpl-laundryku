<?php

namespace App\Http\Requests\Api\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MitraRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users')->where(fn ($query) => $query->where('role', User::ROLE_STORE_ADMIN)),
            ],
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20|regex:/^[+0-9\-\s()]+$/',
            'store_name' => 'required|string|max:255',
            'store_address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Email ini sudah terdaftar sebagai mitra.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'password.min' => 'Password minimal terdiri dari 8 karakter.',
            'store_name.required' => 'Nama toko wajib diisi.',
        ];
    }
}
