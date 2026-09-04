<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $category = $this->route('category');

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'alpha_dash:ascii', 'max:140', Rule::unique('categories', 'slug')->ignore(is_object($category) ? $category->getKey() : null)],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id', Rule::notIn([is_object($category) ? $category->getKey() : null])],
            'description' => ['nullable', 'string', 'max:10000'], 'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,svg', 'max:4096'],
            'seo_title' => ['nullable', 'string', 'max:255'], 'seo_description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'], 'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
