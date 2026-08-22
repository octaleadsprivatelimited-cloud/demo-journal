<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ReviewAssigned;
use App\Notifications\ReviewAssignedNotification;

class NotifyReviewerOfAssignment
{
    public function handle(ReviewAssigned $event): void
    {
        $event->review->loadMissing(['reviewer', 'article']);
        $event->review->reviewer?->notify(new ReviewAssignedNotification($event->review));
    }
}
