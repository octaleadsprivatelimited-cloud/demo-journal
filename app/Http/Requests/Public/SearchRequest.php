<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

class SearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:160'],
            'title' => ['nullable', 'string', 'max:160'],
            'author' => ['nullable', 'string', 'max:160'],
            'category' => ['nullable', 'string', 'max:160'],
            'tag' => ['nullable', 'string', 'max:160'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'year' => ['nullable', 'integer', 'min:1800', 'max:2200'],
            'volume' => ['nullable', 'string', 'max:40'], 'issue' => ['nullable', 'string', 'max:40'],
            'publication_type' => ['nullable', 'in:article,research,review,essay,case-study,editorial'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['q', 'title'] as $field) {
            if ($this->filled($field)) {
                $this->merge([$field => trim((string) $this->input($field))]);
            }
        }
    }
}
