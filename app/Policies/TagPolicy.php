<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tag;
use App\Models\User;

class TagPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Tag $tag): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole('super-admin', 'admin', 'editor');
    }

    public function update(User $user, Tag $tag): bool
    {
        return $user->hasAnyRole('super-admin', 'admin', 'editor');
    }

    public function delete(User $user, Tag $tag): bool
    {
        return $user->hasAnyRole('super-admin', 'admin');
    }

    public function restore(User $user, Tag $tag): bool
    {
        return $user->hasAnyRole('super-admin', 'admin');
    }
}
