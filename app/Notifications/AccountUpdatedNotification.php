<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
class AccountUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    public function __construct(public bool $emailChanged, public bool $passwordChanged) { $this->afterCommit(); }
    public function via(object $notifiable): array { return ['mail']; }
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->markdown('notifications::email', ['templateType' => 'account'])->subject('Administrator account settings changed')
            ->line('Your journal administrator '.($this->emailChanged&&$this->passwordChanged?'email address and password':($this->emailChanged?'email address':'password')).' were updated.')
            ->line('If you did not make this change, contact the journal immediately.')
            ->action('Open journal',url('/'));
    }
}
