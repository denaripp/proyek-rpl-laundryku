<?php

namespace App\Http\Requests\Review;

use Illuminate\Foundation\Http\FormRequest;

class CreateReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && $user->hasPermissionTo('create_review');
    }

    public function rules(): array
    {
        return [
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
        ];
    }
}
