<?php

declare(strict_types=1);

namespace App\Http\Requests\Author;

use Illuminate\Foundation\Http\FormRequest;

final class SubmitArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'confirmation' => ['accepted'],
            'cover_letter' => ['nullable', 'string', 'max:10000'],
            'change_summary' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
