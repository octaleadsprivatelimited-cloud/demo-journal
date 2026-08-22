<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Article;
use App\Models\Media;
use App\Models\User;

class MediaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole('super-admin', 'admin', 'editor', 'author', 'reviewer');
    }

    public function view(User $user, Media $media): bool
    {
        if ($user->hasAnyRole('super-admin', 'admin', 'editor') || $media->uploaded_by_id === $user->getKey()) {
            return true;
        }

        $articleMorph = (new Article)->getMorphClass();

        return $media->mediable_type === $articleMorph
            && ($media->mediable()->first()?->isOwnedBy($user) ?? false);
    }

    public function create(User $user): bool
    {
        return $user->isActive() && $user->hasAnyRole('super-admin', 'admin', 'editor', 'author', 'reviewer');
    }

    public function update(User $user, Media $media): bool
    {
        return $user->hasAnyRole('super-admin', 'admin', 'editor') || $media->uploaded_by_id === $user->getKey();
    }

    public function delete(User $user, Media $media): bool
    {
        return $this->update($user, $media);
    }
}
