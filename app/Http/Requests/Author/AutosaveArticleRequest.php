<?php

declare(strict_types=1);

namespace App\Http\Requests\Author;

use Illuminate\Foundation\Http\FormRequest;

final class AutosaveArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['keywords', 'references'] as $field) {
            if (is_string($this->input($field))) {
                $parts = preg_split($field === 'keywords' ? '/[,\r\n]+/' : '/\r\n|\r|\n/', $this->input($field)) ?: [];
                $this->merge([$field => array_values(array_filter(array_map('trim', $parts)))]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'updated_at' => ['nullable', 'date'],
            'title' => ['sometimes', 'required', 'string', 'max:240'],
            'subtitle' => ['sometimes', 'nullable', 'string', 'max:300'],
            'excerpt' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'abstract' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'content' => ['sometimes', 'nullable', 'string', 'max:1000000'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'publication_type' => ['sometimes', 'in:article,research,review,essay,case-study,editorial'],
            'keywords' => ['sometimes', 'nullable', 'array', 'max:30'],
            'keywords.*' => ['string', 'max:80'],
            'references' => ['sometimes', 'nullable', 'array', 'max:100'],
            'references.*' => ['string', 'max:2000'],
        ];
    }
}
