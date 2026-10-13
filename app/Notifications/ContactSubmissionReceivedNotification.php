<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ContactSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
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
            ->subject('New journal enquiry #'.$this->submission->getKey())
            ->greeting('Hello '.($notifiable->name ?? 'Editorial office').',')
            ->replyTo($this->submission->email)
            ->line('A new enquiry has been received through the journal contact form.')
            ->line('Enquiry reference: #'.$this->submission->getKey())
            ->line('Read the message and sender details in the admin inbox. The full enquiry is saved there for the editorial team.')
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
