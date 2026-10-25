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

    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('score') && !$this->has('rating')) {
            $merge['rating'] = $this->input('score');
        }
        if ($this->has('review') && !$this->has('comment')) {
            $merge['comment'] = $this->input('review');
        }
        if (!empty($merge)) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        return [
            'rating' => 'required|integer|min:1|max:5',
            'score' => 'nullable|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
            'review' => 'nullable|string|max:2000',
        ];
    }
}
