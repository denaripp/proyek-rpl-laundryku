<?php

namespace App\Http\Requests\Refund;

use Illuminate\Foundation\Http\FormRequest;

class CreateRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user !== null;
    }

    public function rules(): array
    {
        return [
            'order_id' => 'required|integer|exists:orders,id',
            'payment_id' => 'required|integer|exists:payments,id',
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:2000',
            'method' => 'required|string|in:bank_transfer,wallet,cod,manual',
        ];
    }
}
