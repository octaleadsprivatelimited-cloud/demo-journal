<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Review;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReviewCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Review $review, public bool $forAuthor)
    {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $article = $this->review->article()->firstOrFail();

        if ($this->forAuthor) {
            return (new MailMessage)
                ->subject('Review update: '.$article->title)
                ->greeting('Hello '.$notifiable->name.',')
                ->line('A reviewer report has been received for your manuscript.')
                ->line('Any author-facing feedback is available in your manuscript workspace.')
                ->action('View manuscript', route('author.articles.show', $article));
        }

        return (new MailMessage)
            ->subject('Review completed: '.$article->title)
            ->greeting('Hello '.$notifiable->name.',')
            ->line('A reviewer has completed their report for this manuscript.')
            ->line('Open the editorial review record to assess the recommendation and next action.')
            ->action('Open review', route('admin.reviews.show', $this->review));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $article = $this->review->article()->firstOrFail();

        return [
            'type' => 'review_completed',
            'audience' => $this->forAuthor ? 'author' : 'editorial',
            'review_id' => $this->review->getKey(),
            'article_id' => $article->public_id,
            'title' => $article->title,
            'recommendation' => $this->review->recommendation?->value,
        ];
    }
}
