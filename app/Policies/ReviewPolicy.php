<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ReviewStatus;
use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('super-admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('articles.review');
    }

    public function view(User $user, Review $review): bool
    {
        return $this->canManageEditorialReviews($user)
            || ($user->hasPermission('articles.review') && $review->reviewer_id === $user->getKey());
    }

    public function create(User $user): bool
    {
        return $this->canManageEditorialReviews($user);
    }

    public function update(User $user, Review $review): bool
    {
        return $this->canManageEditorialReviews($user)
            || ($user->hasPermission('articles.review')
                && $review->reviewer_id === $user->getKey()
                && in_array($review->status, [ReviewStatus::Assigned, ReviewStatus::InProgress], true));
    }

    public function complete(User $user, Review $review): bool
    {
        return $user->hasPermission('articles.review')
            && $review->reviewer_id === $user->getKey()
            && in_array($review->status, [ReviewStatus::Assigned, ReviewStatus::InProgress], true);
    }

    public function delete(User $user, Review $review): bool
    {
        return $this->canManageEditorialReviews($user);
    }

    private function canManageEditorialReviews(User $user): bool
    {
        return $user->hasPermission('articles.review')
            && $user->hasPermission('articles.update-any');
    }
}
