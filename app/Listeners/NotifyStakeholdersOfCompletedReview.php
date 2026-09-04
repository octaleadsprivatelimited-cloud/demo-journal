<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ReviewCompleted;
use App\Models\User;
use App\Notifications\ReviewCompletedNotification;
use App\Services\ManuscriptWorkflowService;
use Illuminate\Support\Facades\Notification;

class NotifyStakeholdersOfCompletedReview
{
    public function handle(ReviewCompleted $event): void
    {
        $event->review->loadMissing(['article.creator', 'assignedBy']);

        $editors = User::query()->active()
            ->where(fn ($query) => $query->whereHas('roles', fn ($roles) => $roles->whereIn('slug', ['super-admin', 'admin']))->orWhere('id', $event->review->article->assigned_editor_id))
            ->get();

        $assignedBy = $event->review->assignedBy;
        if ($assignedBy?->isActive() && (! $event->review->article->workflow || app(ManuscriptWorkflowService::class)->canEdit($assignedBy, $event->review->article))) {
            $editors->push($assignedBy);
        }

        Notification::send(
            $editors->unique('id')->values(),
            new ReviewCompletedNotification($event->review, forAuthor: false),
        );

        $event->review->article?->creator?->notify(new ReviewCompletedNotification($event->review, forAuthor: true));
    }
}
