<?php

namespace App\Http\Requests\Order;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is handled in the controller via Gate::authorize('view', $store).
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(array_column(OrderStatus::cases(), 'value'))],
            'reason' => 'nullable|string|max:1000',
            'metadata' => 'nullable|array',
        ];
    }
}
