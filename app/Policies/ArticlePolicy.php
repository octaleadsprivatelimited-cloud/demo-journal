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
        if ($user->hasRole('super-admin')) {
            return true;
        }
        $article = request()->route('article');
        if ($article instanceof Article && $user->hasRole('editor') && ! $user->hasRole('admin') && $article->assigned_editor_id !== $user->id) {
            return false;
        }

        return null;
    }

    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Article $article): bool
    {
        if ($user && $user->hasAnyRole('admin', 'super-admin')) {
            return true;
        }
        if ($user && $user->hasRole('editor')) {
            return $article->assigned_editor_id === $user->id;
        }

        return $article->status === ArticleStatus::Published || ($user && $article->created_by_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->isActive() && $user->hasPermission('articles.create');
    }

    public function update(User $user, Article $article): bool
    {
        if ($article->workflow && ! in_array($article->workflow->stage, ['draft', 'returned', 'minor_revision', 'major_revision'], true)) {
            return false;
        }
        if ($user->hasRole('editor') && ! $user->hasRole('admin') && $article->assigned_editor_id !== $user->id) {
            return false;
        }
        if ($user->hasPermission('articles.update-any')) {
            return true;
        }

        return $user->hasPermission('articles.update')
            && $article->isOwnedBy($user)
            && in_array($article->status, [ArticleStatus::Draft, ArticleStatus::RevisionRequired, ArticleStatus::Rejected], true);
    }

    public function delete(User $user, Article $article): bool
    {
        return $user->hasPermission('articles.delete')
            || ($user->hasPermission('articles.update')
                && $article->isOwnedBy($user)
                && in_array($article->status, [ArticleStatus::Draft, ArticleStatus::Rejected], true));
    }

    public function restore(User $user, Article $article): bool
    {
        return $user->hasPermission('articles.delete');
    }

    public function forceDelete(User $user, Article $article): bool
    {
        return false;
    }

    public function submit(User $user, Article $article): bool
    {
        return $user->hasPermission('articles.submit')
            && $article->isOwnedBy($user)
            && in_array($article->status, [ArticleStatus::Draft, ArticleStatus::RevisionRequired, ArticleStatus::Rejected], true);
    }

    public function transition(User $user, Article $article): bool
    {
        return $user->hasPermission('articles.review');
    }

    public function publish(User $user, Article $article): bool
    {
        return $user->hasPermission('articles.publish');
    }

    public function review(User $user, Article $article): bool
    {
        return $user->hasPermission('articles.review')
            && $article->reviews()->where('reviewer_id', $user->getKey())->exists();
    }

    public function manageSeo(User $user, Article $article): bool
    {
        return $user->hasPermission('articles.update-any');
    }
}
