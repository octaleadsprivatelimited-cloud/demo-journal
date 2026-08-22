<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

final class StoreArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole('author', 'editor', 'admin', 'super-admin') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:240'],
            'subtitle' => ['nullable', 'string', 'max:300'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'abstract' => ['required', 'string', 'max:10000'],
            'content' => ['required', 'string', 'max:1000000'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'publication_type' => ['required', 'string', 'max:80'],
            'doi' => ['nullable', 'string', 'max:255'],
            'keywords' => ['nullable', 'array', 'max:20'],
            'keywords.*' => ['string', 'max:80'],
            'references' => ['nullable', 'array', 'max:250'],
            'references.*' => ['string', 'max:2000'],
            'tag_ids' => ['nullable', 'array', 'max:20'],
            'tag_ids.*' => ['integer', 'distinct', 'exists:tags,id'],
        ];
    }
}
