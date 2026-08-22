<?php

declare(strict_types=1);

namespace App\Http\Requests\Reviewer;

use App\Enums\ReviewRecommendation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CompleteReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'recommendation' => ['required', Rule::enum(ReviewRecommendation::class)],
            'comments_to_author' => ['required', 'string', 'min:40', 'max:20000'],
            'confidential_comments' => ['nullable', 'string', 'max:20000'],
            'comments' => ['nullable', 'array', 'max:100'],
            'comments.*.body' => ['required', 'string', 'max:5000'],
            'comments.*.location' => ['nullable', 'string', 'max:255'],
            'confirmation' => ['accepted'],
        ];
    }
}
