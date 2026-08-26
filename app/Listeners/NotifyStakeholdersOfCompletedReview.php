<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ReviewCompleted;
use App\Models\User;
use App\Notifications\ReviewCompletedNotification;
use Illuminate\Support\Facades\Notification;

class NotifyStakeholdersOfCompletedReview
{
    public function handle(ReviewCompleted $event): void
    {
        $event->review->loadMissing(['article.creator', 'assignedBy']);

        $editors = User::query()->active()
            ->whereHas('roles', fn ($query) => $query->whereIn('slug', ['super-admin', 'admin', 'editor']))
            ->get();

        $assignedBy = $event->review->assignedBy;
        if ($assignedBy?->isActive()) {
            $editors->push($assignedBy);
        }

        Notification::send(
            $editors->unique('id')->values(),
            new ReviewCompletedNotification($event->review, forAuthor: false),
        );

        $event->review->article?->creator?->notify(new ReviewCompletedNotification($event->review, forAuthor: true));
    }
}
