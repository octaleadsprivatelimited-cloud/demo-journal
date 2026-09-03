<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ArticleStatusChanged;
use App\Services\WorkflowNotificationService;

class NotifyAuthorOfArticleStatus
{
    public function handle(ArticleStatusChanged $event, WorkflowNotificationService $notifications): void
    {
        $creator = $event->article->creator;
        if (! $creator) { return; }
        if (! in_array($event->to->value, ['minor_revision_requested', 'major_revision_requested', 'revision_required', 'accepted', 'approved', 'rejected', 'proof_sent_to_author', 'published'], true)) { return; }
        $notifications->notify($creator, $event->article, 'manuscript_status_changed', match ($event->to->value) {
            'minor_revision_requested' => 'minor_revision', 'major_revision_requested' => 'major_revision', 'rejected' => 'rejection', 'accepted', 'approved' => 'acceptance', 'published' => 'article_published', default => 'submission_returned',
        }, 'Manuscript update – '.$event->article->public_id, $event->note ?: 'Your manuscript status is now '.$event->to->label().'.', route('author.articles.show', $event->article), ['action_id' => $event->article->updated_at?->getTimestamp(), 'priority' => in_array($event->to->value, ['minor_revision_requested', 'major_revision_requested', 'rejected'], true) ? 'important' : 'normal']);
    }
}
