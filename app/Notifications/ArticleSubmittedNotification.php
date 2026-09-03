<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Article;
use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ArticleSubmittedNotification extends Notification implements ShouldQueue
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
            ->subject('New article submission: '.$this->article->title)
            ->greeting('Hello '.$notifiable->name.',')
            ->line('A new manuscript has been submitted for editorial review.')
            ->line('Submission round: '.$this->submission->round)
            ->action('Review submission', rtrim((string) config('app.url'), '/').'/admin/submissions/'.$this->submission->getKey());
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'article_submitted',
            'article_id' => $this->article->public_id,
            'submission_id' => $this->submission->getKey(),
            'title' => $this->article->title,
        ];
    }
}
