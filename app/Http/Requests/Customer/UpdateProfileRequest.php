<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|max:255|unique:users,email,' . $userId,
            'phone' => 'sometimes|nullable|string|max:20|regex:/^[+0-9\-\s()]+$/',
            'date_of_birth' => 'nullable|string|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Phone number may only contain digits, spaces, hyphens, and parentheses.',
        ];
    }
}
