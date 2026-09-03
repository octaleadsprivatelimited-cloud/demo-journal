<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\CommentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CommentModerationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('moderateComments') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(CommentStatus::class)],
        ];
    }
}
