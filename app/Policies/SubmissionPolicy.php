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
        return $this->canManageEditorialSubmissions($user)
            || $user->hasPermission('articles.submit');
    }

    public function view(User $user, Submission $submission): bool
    {
        return $this->canManageEditorialSubmissions($user)
            || ($user->hasPermission('articles.submit')
                && $submission->article()->firstOrFail()->isOwnedBy($user));
    }

    public function update(User $user, Submission $submission): bool
    {
        return $this->canManageEditorialSubmissions($user);
    }

    private function canManageEditorialSubmissions(User $user): bool
    {
        return $user->hasPermission('articles.review')
            && $user->hasPermission('articles.update-any');
    }
}
