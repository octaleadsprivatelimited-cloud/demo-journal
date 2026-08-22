<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ContactSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContactSubmissionReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ContactSubmission $submission)
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
            ->subject('New contact enquiry: '.$this->submission->subject)
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->submission->name.' submitted a new contact enquiry.')
            ->line('Category: '.($this->submission->category ?: 'General'))
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
