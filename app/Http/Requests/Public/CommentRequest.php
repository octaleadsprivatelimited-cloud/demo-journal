<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'guest_name' => [Rule::requiredIf(fn (): bool => $this->user() === null), 'nullable', 'string', 'min:2', 'max:120'],
            'guest_email' => [Rule::requiredIf(fn (): bool => $this->user() === null), 'nullable', 'email:rfc', 'max:254'],
            'body' => ['required', 'string', 'min:10', 'max:5000'],
            'comment_website' => ['nullable', 'string', 'max:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'guest_name' => $this->filled('guest_name') ? trim((string) $this->input('guest_name')) : null,
            'guest_email' => $this->filled('guest_email') ? strtolower(trim((string) $this->input('guest_email'))) : null,
            'body' => trim((string) $this->input('body')),
        ]);
    }
}
