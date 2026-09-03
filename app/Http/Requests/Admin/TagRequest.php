<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tag = $this->route('tag');

        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'alpha_dash:ascii', 'max:120', Rule::unique('tags', 'slug')->ignore(is_object($tag) ? $tag->getKey() : null)],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
