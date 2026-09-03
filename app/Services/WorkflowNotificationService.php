<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\DeliverWorkflowEmail;
use App\Models\Article;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Notifications\WorkflowInAppNotification;
use Illuminate\Support\Str;

final class WorkflowNotificationService
{
    /** @param array<string, mixed> $context */
    public function notify(User $recipient, Article $article, string $event, string $template, string $subject, string $message, string $actionUrl, array $context = []): void
    {
        $key = hash('sha256', implode('|', [$event, $article->getKey(), $recipient->getKey(), $context['action_id'] ?? $article->updated_at?->getTimestamp() ?? ''])) ;
        $payload = [
            'type' => $event,
            'priority' => $context['priority'] ?? 'normal',
            'title' => $subject,
            'message' => $message,
            'article_id' => $article->public_id,
            'action_url' => $actionUrl,
            'recipient_name' => $recipient->name,
            'manuscript_id' => $article->public_id,
            'manuscript_title' => $article->title,
            'status' => $article->status->label(),
            'journal_name' => config('app.name'),
        ] + $context;

        $delivery = NotificationDelivery::query()->firstOrCreate(
            ['idempotency_key' => $key],
            [
                'article_id' => $article->getKey(),
                'recipient_id' => $recipient->getKey(),
                'recipient_email' => $recipient->email,
                'event_type' => $event,
                'template' => $template,
                'subject' => $subject,
                'status' => 'queued',
                'queued_at' => now(),
                'payload' => $payload,
            ],
        );

        if (! $delivery->wasRecentlyCreated) { return; }

        // A database notification remains available even if email configuration
        // is absent or a provider temporarily fails.
        $recipient->notify(new WorkflowInAppNotification($payload));
        DeliverWorkflowEmail::dispatch($delivery->getKey())->afterCommit();
    }
}
