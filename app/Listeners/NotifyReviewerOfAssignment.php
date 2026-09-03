<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ReviewAssigned;
use App\Services\WorkflowNotificationService;

class NotifyReviewerOfAssignment
{
    public function handle(ReviewAssigned $event, WorkflowNotificationService $notifications): void
    {
        $event->review->loadMissing(['reviewer', 'article']);
        $reviewer = $event->review->reviewer;
        if ($reviewer) {
            $article = $event->review->article;
            $notifications->notify($reviewer, $article, 'reviewer_invited', 'reviewer_invitation', 'Review invitation – '.$article->public_id, 'You have been invited to review a manuscript.', route('reviewer.reviews.show', $event->review), ['action_id' => $event->review->getKey(), 'priority' => 'important', 'due_date' => optional($event->review->due_at)->toDateString()]);
        }
    }
}
