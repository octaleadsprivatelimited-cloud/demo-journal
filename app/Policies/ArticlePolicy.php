<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\User;

class ArticlePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('super-admin') ? true : null;
    }

    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Article $article): bool
    {
        return $article->status === ArticleStatus::Published
            || ($user && ($article->isOwnedBy($user) || $user->hasAnyRole('admin', 'editor', 'reviewer')));
    }

    public function create(User $user): bool
    {
        return $user->isActive() && $user->hasAnyRole('admin', 'editor', 'author');
    }

    public function update(User $user, Article $article): bool
    {
        if ($user->hasAnyRole('admin', 'editor')) {
            return true;
        }

        return $article->isOwnedBy($user)
            && in_array($article->status, [ArticleStatus::Draft, ArticleStatus::RevisionRequired, ArticleStatus::Rejected], true);
    }

    public function delete(User $user, Article $article): bool
    {
        return $user->hasRole('admin')
            || ($article->isOwnedBy($user)
                && in_array($article->status, [ArticleStatus::Draft, ArticleStatus::Rejected], true));
    }

    public function restore(User $user, Article $article): bool
    {
        return $user->hasRole('admin');
    }

    public function forceDelete(User $user, Article $article): bool
    {
        return false;
    }

    public function submit(User $user, Article $article): bool
    {
        return $article->isOwnedBy($user)
            && in_array($article->status, [ArticleStatus::Draft, ArticleStatus::RevisionRequired, ArticleStatus::Rejected], true);
    }

    public function transition(User $user, Article $article): bool
    {
        return $user->hasAnyRole('admin', 'editor');
    }

    public function review(User $user, Article $article): bool
    {
        return $article->reviews()->where('reviewer_id', $user->getKey())->exists();
    }

    public function manageSeo(User $user, Article $article): bool
    {
        return $user->hasAnyRole('admin', 'editor');
    }
}
