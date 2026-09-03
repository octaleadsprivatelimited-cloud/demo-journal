<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ContactStatus;
use App\Models\ContactSubmission;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class StoreContactSubmission
{
    /** @param array<string, mixed> $data */
    public function execute(array $data, ?User $user = null, ?string $ipAddress = null, ?string $userAgent = null): ContactSubmission
    {
        $submission = ContactSubmission::query()->create([
            ...Arr::only($data, ['name', 'phone', 'subject', 'message', 'category']),
            'email' => Str::lower(trim((string) $data['email'])),
            'user_id' => $user?->getKey(),
            'status' => ContactStatus::New,
            'ip_hash' => $ipAddress ? hash_hmac('sha256', $ipAddress, (string) config('app.key')) : null,
            'user_agent' => Str::limit((string) $userAgent, 1000, ''),
        ]);

        return $submission;
    }
}
