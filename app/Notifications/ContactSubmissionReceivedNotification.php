<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ContactSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;

class ContactSubmissionReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ContactSubmission $submission)
    {
        $this->onQueue('mail')->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof AnonymousNotifiable ? ['mail'] : ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->markdown('notifications::email', ['templateType' => 'contact'])
            ->subject('New contact enquiry: '.$this->submission->subject)
            ->greeting('Hello '.($notifiable->name ?? 'Editorial office').',')
            ->replyTo($this->submission->email, $this->submission->name)
            ->line($this->submission->name.' submitted a new contact enquiry.')
            ->line('Email: '.$this->submission->email)
            ->line('Category: '.($this->submission->category ?: 'General'))
            ->line('Message: '.$this->submission->message)
            ->action('View enquiry', rtrim((string) config('app.url'), '/').'/admin/contact-submissions/'.$this->submission->getKey());
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'contact_submission_received',
            'submission_id' => $this->submission->getKey(),
            'name' => $this->submission->name,
            'subject' => $this->submission->subject,
        ];
    }
}
