<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Author;
use App\Models\User;

class AuthorPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('super-admin') ? true : null;
    }

    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Author $author): bool
    {
        return $author->is_active || ($user && $this->update($user, $author));
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('authors.manage');
    }

    public function update(User $user, Author $author): bool
    {
        return $user->hasPermission('authors.manage') || $author->user_id === $user->getKey();
    }

    public function delete(User $user, Author $author): bool
    {
        return $user->hasPermission('authors.manage')
            && $user->hasPermission('articles.delete');
    }

    public function restore(User $user, Author $author): bool
    {
        return $user->hasPermission('authors.manage')
            && $user->hasPermission('articles.delete');
    }
}
