<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ArticleActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['publish', 'unpublish', 'schedule', 'production', 'withdraw', 'feature', 'unfeature', 'trend', 'untrend', 'approve', 'reject', 'revision', 'assign_editor', 'assign_reviewer'])],
            'note' => ['nullable', 'string', 'max:5000'],
            'scheduled_for' => ['required_if:action,schedule', 'nullable', 'date', 'after:now'],
            'user_id' => ['required_if:action,assign_editor,assign_reviewer', 'nullable', 'integer', 'exists:users,id'],
            'due_at' => ['nullable', 'date', 'after:today'],
        ];
    }
}
