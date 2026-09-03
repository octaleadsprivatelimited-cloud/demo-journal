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
            || ($user && ($article->isOwnedBy($user) || $user->hasPermission('articles.view-unpublished')));
    }

    public function create(User $user): bool
    {
        return $user->isActive() && $user->hasPermission('articles.create');
    }

    public function update(User $user, Article $article): bool
    {
        if ($user->hasPermission('articles.update-any')) {
            return true;
        }

        return $user->hasPermission('articles.update')
            && $article->isOwnedBy($user)
            && in_array($article->status, [
                ArticleStatus::Draft, ArticleStatus::RevisionRequired, ArticleStatus::Rejected,
                ArticleStatus::ReturnedForSubmissionCorrection, ArticleStatus::MinorRevisionRequested,
                ArticleStatus::MajorRevisionRequested,
            ], true);
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
            && in_array($article->status, [
                ArticleStatus::Draft, ArticleStatus::RevisionRequired, ArticleStatus::Rejected,
                ArticleStatus::ReturnedForSubmissionCorrection, ArticleStatus::MinorRevisionRequested,
                ArticleStatus::MajorRevisionRequested,
            ], true);
    }

    public function transition(User $user, Article $article): bool
    {
        return $user->hasPermission('articles.review');
    }

    public function initialCheck(User $user, Article $article): bool
    {
        return $user->hasPermission('manuscripts.initial-check') && $article->status === ArticleStatus::Submitted;
    }

    public function decide(User $user, Article $article): bool
    {
        return $user->hasPermission('manuscripts.decide')
            && in_array($article->status, [ArticleStatus::AwaitingEditorDecision, ArticleStatus::UnderReview, ArticleStatus::UnderReReview], true);
    }

    public function production(User $user, Article $article): bool
    {
        return $user->hasPermission('manuscripts.production') && $article->status === ArticleStatus::Accepted;
    }

    public function publish(User $user, Article $article): bool
    {
        return $user->hasPermission('manuscripts.publish') || $user->hasPermission('articles.publish');
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
