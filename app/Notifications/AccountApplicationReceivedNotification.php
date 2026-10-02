<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

final class AccountApplicationReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public User $applicant)
    {
        $this->onQueue('mail')->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return $notifiable instanceof AnonymousNotifiable ? ['mail'] : ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->markdown('notifications::email', ['templateType' => 'application'])
            ->subject('Account application awaiting approval: '.$this->applicant->name)
            ->greeting('Hello '.($notifiable->name ?? 'Editorial office').',')
            ->line($this->applicant->name.' ('.$this->applicant->email.') has applied for a '.Str::headline($this->applicant->requested_role).' account.')
            ->line('The account remains inactive until a Super Admin approves the application.')
            ->action('Review applications', rtrim((string) config('app.url'), '/').'/admin/users');
    }

    public function toArray(object $notifiable): array
    {
        return ['type' => 'account_application_received', 'user_id' => $this->applicant->id, 'name' => $this->applicant->name, 'requested_role' => $this->applicant->requested_role];
    }
}
