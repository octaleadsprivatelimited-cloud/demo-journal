<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ContactSubmission;
use App\Models\User;

class ContactSubmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('contacts.manage');
    }

    public function view(User $user, ContactSubmission $submission): bool
    {
        return $user->hasPermission('contacts.manage');
    }

    public function update(User $user, ContactSubmission $submission): bool
    {
        return $user->hasPermission('contacts.manage');
    }

    public function delete(User $user, ContactSubmission $submission): bool
    {
        return $user->hasPermission('contacts.manage');
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('contacts.manage');
    }
}
