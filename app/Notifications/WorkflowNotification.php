<?php

namespace App\Notifications;

use App\Models\Article;
use App\Models\Review;
use App\Services\EmailPresentation;
use App\Services\WorkflowSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkflowNotification extends Notification implements ShouldQueue
{
    use Queueable;

    // Defaults also keep notifications queued by earlier releases readable.
    public ?string $articleTitle = null;

    public ?string $articleSlug = null;

    public ?string $manuscriptId = null;

    public ?string $stage = null;

    public ?string $deadline = null;

    public ?int $reviewId = null;

    public ?int $reviewerId = null;

    public function __construct(public int $articleId, public string $message, ?Review $review = null)
    {
        $article = Article::withTrashed()->with('workflow')->find($articleId);
        $this->articleTitle = $article?->title;
        $this->articleSlug = $article?->slug;
        $this->manuscriptId = $article?->workflow?->manuscript_id;
        $this->stage = $article?->workflow?->stage;
        $this->deadline = ($review
            ? ($review->status->value === 'assigned' ? $review->invitation_deadline : $review->due_at)
            : $article?->workflow?->deadline)?->toFormattedDateString();
        $this->reviewId = $review?->id;
        $this->reviewerId = $review?->reviewer_id;
        $this->onQueue('mail')->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return ['article_id' => $this->articleId, 'title' => $this->articleTitle, 'message' => $this->message, 'stage' => $this->stage, 'deadline' => $this->deadline, 'url' => $this->urlFor($notifiable)];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->markdown('notifications::email', ['templateType' => EmailPresentation::type($this->message)])
            ->subject(config('workflow.journal').' — '.$this->message.($this->articleTitle ? ': '.$this->articleTitle : ''))
            ->line(app(WorkflowSettings::class)->values()['notification_intro'])
            ->line($this->message)
            ->when($this->articleTitle, fn (MailMessage $mail) => $mail->line('Manuscript: '.$this->articleTitle))
            ->when($this->manuscriptId, fn (MailMessage $mail) => $mail->line('Manuscript ID: '.$this->manuscriptId))
            ->when($this->stage, fn (MailMessage $mail) => $mail->line('Stage: '.str($this->stage)->headline()))
            ->when($this->deadline, fn (MailMessage $mail) => $mail->line('Action due: '.$this->deadline))
            ->line('Sign in to view the manuscript details.')
            ->action('Open manuscript', $this->urlFor($notifiable));
    }

    private function urlFor(object $notifiable): string
    {
        if ($this->reviewId && $this->reviewerId === $notifiable->id) {
            return route('reviewer.reviews.show', $this->reviewId);
        }
        if ($notifiable->hasRole('reviewer') && ! $notifiable->hasAnyRole('author', 'contributor', 'editor', 'admin', 'super-admin')) {
            return route('reviewer.dashboard');
        }

        return $this->articleSlug ? route('workflow.show', $this->articleSlug) : route('workflow.index');
    }
}
