<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore(is_object($user) ? $user->getKey() : null)],
            'phone' => ['nullable', 'string', 'max:32'], 'organization' => ['nullable', 'string', 'max:160'],
            'designation' => ['nullable', 'string', 'max:120'], 'password' => [is_object($user) ? 'nullable' : 'required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()->uncompromised()],
            'roles' => ['nullable', 'array'], 'roles.*' => ['integer', 'distinct', 'exists:roles,id'],
            'is_active' => ['sometimes', 'boolean'], 'email_verified' => ['sometimes', 'boolean'],
            'biography' => ['nullable', 'string', 'max:3000'], 'author_verified' => ['sometimes', 'boolean'],
        ];
    }
}
