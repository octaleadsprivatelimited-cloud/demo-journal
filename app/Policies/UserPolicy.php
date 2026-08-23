<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('super-admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, User $managedUser): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, User $managedUser): bool
    {
        return false;
    }

    public function delete(User $user, User $managedUser): bool
    {
        return false;
    }

    public function approve(User $user, User $managedUser): bool
    {
        return false;
    }

    public function reject(User $user, User $managedUser): bool
    {
        return false;
    }
}
