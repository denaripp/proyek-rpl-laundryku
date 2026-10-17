<?php

namespace App\Http\Requests\Refund;

use Illuminate\Foundation\Http\FormRequest;

class ProcessRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && $user->hasPermissionTo('process_refund');
    }

    public function rules(): array
    {
        return [
            'note' => 'nullable|string|max:2000',
        ];
    }
}
