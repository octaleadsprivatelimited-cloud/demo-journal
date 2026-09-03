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
        return $user->hasPermission('media.manage');
    }

    public function view(User $user, Media $media): bool
    {
        if (! $user->hasPermission('media.manage')) {
            return false;
        }

        if ($user->hasPermission('articles.update-any') || $media->uploaded_by_id === $user->getKey()) {
            return true;
        }

        $articleMorph = (new Article)->getMorphClass();

        return $media->mediable_type === $articleMorph
            && ($media->mediable()->first()?->isOwnedBy($user) ?? false);
    }

    public function create(User $user): bool
    {
        return $user->isActive() && $user->hasPermission('media.manage');
    }

    public function update(User $user, Media $media): bool
    {
        return $user->hasPermission('media.manage')
            && ($user->hasPermission('articles.update-any') || $media->uploaded_by_id === $user->getKey());
    }

    public function delete(User $user, Media $media): bool
    {
        return $this->update($user, $media);
    }
}
