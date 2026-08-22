<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class MediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'file' => [$this->isMethod('post') ? 'required' : 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx', 'max:25600'],
            'alt_text' => ['nullable', 'string', 'max:255'], 'caption' => ['nullable', 'string', 'max:2000'],
            'collection' => ['nullable', 'alpha_dash:ascii', 'max:64'],
        ];
    }
}
