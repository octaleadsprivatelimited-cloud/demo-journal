<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ArticleSubmitted;
use App\Notifications\SubmissionReceivedNotification;

class NotifyAuthorOfArticleSubmission
{
    public function handle(ArticleSubmitted $event): void
    {
        $event->submission->loadMissing('submitter');
        $event->submission->submitter?->notify(new SubmissionReceivedNotification($event->article, $event->submission));
    }
}
