<?php

declare(strict_types=1);

namespace App\Http\Requests\Author;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($this->user()->getKey())],
            'phone' => ['nullable', 'string', 'max:32'], 'organization' => ['nullable', 'string', 'max:160'], 'designation' => ['nullable', 'string', 'max:120'],
            'biography' => ['nullable', 'string', 'max:5000'], 'website_url' => ['nullable', 'url:http,https', 'max:2048'],
            'affiliation' => ['nullable', 'string', 'max:255'], 'orcid' => ['nullable', 'regex:/^\d{4}-\d{4}-\d{4}-\d{3}[\dX]$/', Rule::unique('authors', 'orcid')->ignore($this->user()->author?->id)],
            'avatar' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,svg', 'max:4096'],
        ];
    }
}
