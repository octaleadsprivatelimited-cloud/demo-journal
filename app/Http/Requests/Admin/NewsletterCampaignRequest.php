<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class NewsletterCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['subject' => ['required', 'string', 'max:255'], 'preview_text' => ['nullable', 'string', 'max:255'], 'content' => ['required', 'string', 'max:200000'],
            'action' => ['required', Rule::in(['draft', 'send', 'schedule'])], 'scheduled_for' => ['required_if:action,schedule', 'nullable', 'date', 'after:now']];
    }
}
