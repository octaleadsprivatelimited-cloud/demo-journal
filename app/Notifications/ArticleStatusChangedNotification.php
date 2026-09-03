<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\ArticleStatus;
use App\Models\Article;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ArticleStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Article $article, public ArticleStatus $status, public ?string $note = null)
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
        $message = (new MailMessage)
            ->subject("Article {$this->status->label()}: {$this->article->title}")
            ->greeting('Hello '.$notifiable->name.',')
            ->line("Your article status is now {$this->status->label()}.");

        if ($this->note) {
            $message->line($this->note);
        }

        return $message->action('View article', rtrim((string) config('app.url'), '/').'/author/articles/'.$this->article->slug.'/edit');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'article_status_changed',
            'article_id' => $this->article->public_id,
            'title' => $this->article->title,
            'status' => $this->status->value,
            'note' => $this->note,
        ];
    }
}
