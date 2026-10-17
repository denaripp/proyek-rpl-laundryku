<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class AssignCourierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'task_type' => 'required|string|in:pickup,delivery',
        ];
    }
}
