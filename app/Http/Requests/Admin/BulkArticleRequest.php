<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class BulkArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'article_ids' => ['required', 'array', 'min:1', 'max:100'],
            'article_ids.*' => ['integer', 'distinct', 'exists:articles,id'],
            'action' => ['required', Rule::in(['publish', 'delete', 'feature', 'unfeature', 'trend', 'untrend', 'category'])],
            'category_id' => ['required_if:action,category', 'nullable', 'integer', 'exists:categories,id'],
        ];
    }
}
