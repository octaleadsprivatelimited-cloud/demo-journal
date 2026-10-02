<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ContactSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ContactSubmissionReceiptNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ContactSubmission $submission)
    {
        $this->onQueue('mail')->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->markdown('notifications::email', ['templateType' => 'contact'])
            ->subject('We received your enquiry: '.$this->submission->subject)
            ->greeting('Hello '.$this->submission->name.',')
            ->line('Thank you for contacting '.config('publication.name').'.')
            ->line('Your enquiry has been recorded. Our editorial team will review it and respond.');
    }
}
