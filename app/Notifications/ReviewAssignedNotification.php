<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Review;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReviewAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Review $review)
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

        return (new MailMessage)
            ->subject('Review assignment: '.$article->title)
            ->greeting('Hello '.$notifiable->name.',')
            ->line('You have been assigned a manuscript to review.')
            ->when($this->review->due_at, fn (MailMessage $mail) => $mail->line('Due: '.$this->review->due_at->toFormattedDateString()))
            ->action('Open review', rtrim((string) config('app.url'), '/').'/reviewer/reviews/'.$this->review->getKey());
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $article = $this->review->article()->firstOrFail();

        return [
            'type' => 'review_assigned',
            'review_id' => $this->review->getKey(),
            'article_id' => $article->public_id,
            'title' => $article->title,
            'due_at' => $this->review->due_at?->toIso8601String(),
        ];
    }
}
