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
        return $user->hasPermission('users.manage');
    }

    public function view(User $user, User $managedUser): bool
    {
        return $user->is($managedUser) || $user->hasPermission('users.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('users.manage');
    }

    public function update(User $user, User $managedUser): bool
    {
        return $user->is($managedUser) || ($user->hasPermission('users.manage') && ! $managedUser->hasRole('super-admin'));
    }

    public function delete(User $user, User $managedUser): bool
    {
        return ! $user->is($managedUser) && $user->hasPermission('users.manage') && ! $managedUser->hasRole('super-admin');
    }
}
