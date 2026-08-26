<?php

declare(strict_types=1);

namespace App\Enums;

enum ArticleStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case RevisionRequired = 'revision_required';
    case Approved = 'approved';
    case Production = 'production';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Archived = 'archived';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Submitted, self::Withdrawn],
            self::Submitted => [self::UnderReview, self::RevisionRequired, self::Approved, self::Rejected, self::Withdrawn, self::Draft],
            self::UnderReview => [self::RevisionRequired, self::Approved, self::Rejected, self::Withdrawn],
            self::RevisionRequired => [self::Submitted, self::Rejected, self::Withdrawn],
            self::Approved => [self::Production, self::Scheduled, self::Published, self::RevisionRequired, self::Withdrawn, self::Archived],
            self::Production => [self::Scheduled, self::Published, self::Withdrawn, self::Archived],
            self::Scheduled => [self::Published, self::Approved, self::Archived],
            self::Published => [self::Archived],
            self::Rejected => [self::Draft, self::Submitted, self::Archived],
            self::Withdrawn => [self::Draft, self::Archived],
            self::Archived => [self::Draft, self::Published],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::UnderReview => 'Under Review',
            self::RevisionRequired => 'Revision Required',
            self::Production => 'Production',
            self::Withdrawn => 'Withdrawn',
            default => str($this->value)->replace('_', ' ')->title()->toString(),
        };
    }
}
