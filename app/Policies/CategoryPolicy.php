<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Category $category): bool
    {
        return $category->is_active || (bool) $user?->hasAnyRole('admin', 'editor');
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole('super-admin', 'admin', 'editor');
    }

    public function update(User $user, Category $category): bool
    {
        return $user->hasAnyRole('super-admin', 'admin', 'editor');
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->hasAnyRole('super-admin', 'admin');
    }

    public function restore(User $user, Category $category): bool
    {
        return $user->hasAnyRole('super-admin', 'admin');
    }
}
