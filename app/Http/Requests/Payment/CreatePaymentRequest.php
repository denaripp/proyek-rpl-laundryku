<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class CreatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && $user->hasPermissionTo('create_payment');
    }

    public function rules(): array
    {
        return [
            'order_id' => 'required|integer|exists:orders,id',
            'payment_method' => 'nullable|string|in:BANK_TRANSFER,QRIS,CREDIT_CARD,ECHANNEL,ALFAMART,INDOMARET',
        ];
    }
}
