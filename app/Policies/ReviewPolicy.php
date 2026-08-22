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
        return $user->hasAnyRole('admin', 'editor', 'reviewer');
    }

    public function view(User $user, Review $review): bool
    {
        return $user->hasAnyRole('admin', 'editor') || $review->reviewer_id === $user->getKey();
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole('admin', 'editor');
    }

    public function update(User $user, Review $review): bool
    {
        return $user->hasAnyRole('admin', 'editor') || ($review->reviewer_id === $user->getKey()
            && in_array($review->status, [ReviewStatus::Assigned, ReviewStatus::InProgress], true));
    }

    public function complete(User $user, Review $review): bool
    {
        return $review->reviewer_id === $user->getKey()
            && in_array($review->status, [ReviewStatus::Assigned, ReviewStatus::InProgress], true);
    }

    public function delete(User $user, Review $review): bool
    {
        return $user->hasAnyRole('admin', 'editor');
    }
}
