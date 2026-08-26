<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Article;
use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubmissionReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Article $article, public Submission $submission)
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
        return (new MailMessage)
            ->subject('Submission received: '.$this->article->title)
            ->greeting('Hello '.$notifiable->name.',')
            ->line('We have received your manuscript and logged it for editorial assessment.')
            ->line('Submission round: '.$this->submission->round)
            ->line('You will receive another update when its editorial or review status changes.')
            ->action('View manuscript', route('author.articles.show', $this->article));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'submission_received',
            'article_id' => $this->article->public_id,
            'submission_id' => $this->submission->getKey(),
            'title' => $this->article->title,
            'round' => $this->submission->round,
        ];
    }
}
