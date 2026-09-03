<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\ContactStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ContactStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(ContactStatus::class)], 'assigned_to_id' => ['nullable', 'integer', 'exists:users,id']];
    }
}
