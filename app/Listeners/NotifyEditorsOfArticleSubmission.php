<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ArticleSubmitted;
use App\Models\User;
use App\Services\WorkflowNotificationService;

class NotifyEditorsOfArticleSubmission
{
    public function handle(ArticleSubmitted $event, WorkflowNotificationService $notifications): void
    {
        $recipients = User::query()->active()
            ->whereHas('roles', fn ($query) => $query->whereIn('slug', ['super-admin', 'admin', 'editor', 'editorial-assistant']))
            ->get();
        foreach ($recipients as $recipient) {
            $notifications->notify($recipient, $event->article, 'manuscript_submitted', 'submission_received', 'New manuscript submitted – '.$event->article->public_id, 'A manuscript has been submitted and needs editorial attention.', route('admin.submissions.show', $event->submission), ['action_id' => $event->submission->getKey(), 'priority' => 'important']);
        }

        if ($author = $event->article->creator) {
            $notifications->notify($author, $event->article, 'submission_confirmation', 'submission_received', 'Manuscript Submission Received – '.$event->article->public_id, 'Your manuscript has been received by the editorial office.', route('author.articles.show', $event->article), ['action_id' => $event->submission->getKey()]);
        }
    }
}
