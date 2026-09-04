<?php

namespace App\Notifications;

use App\Services\WorkflowSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkflowNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $articleId, public string $message)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return ['article_id' => $this->articleId, 'message' => $this->message, 'url' => route('workflow.index')];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject(config('workflow.journal').' — '.$this->message)->line(app(WorkflowSettings::class)->values()['notification_intro'])->line($this->message)->line('Sign in to view the manuscript details.')->action('Open manuscripts', route('workflow.index'));
    }
}
