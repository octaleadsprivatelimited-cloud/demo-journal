<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Submission;
use App\Models\User;

class SubmissionPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('super-admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole('admin', 'editor', 'author');
    }

    public function view(User $user, Submission $submission): bool
    {
        return $user->hasAnyRole('admin', 'editor')
            || $submission->article()->firstOrFail()->isOwnedBy($user);
    }

    public function update(User $user, Submission $submission): bool
    {
        return $user->hasAnyRole('admin', 'editor');
    }
}
