<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

final class AccountApplicationStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public User $applicant, public string $status)
    {
        $this->onQueue('mail')->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $role = Str::headline($this->applicant->requested_role);
        $applicantCopy = $notifiable instanceof User && $notifiable->is($this->applicant);
        $mail = (new MailMessage)->markdown('notifications::email', ['templateType' => 'application'])
            ->greeting('Hello '.($notifiable->name ?? 'Editorial office').',');

        if ($this->status === 'pending') {
            return $mail->subject('Your '.$role.' account application was received')
                ->line('Thank you for applying to '.config('publication.name').'.')
                ->line('Your '.$role.' application is awaiting Super Admin approval. We will email you when a decision is made.')
                ->line($this->applicant->hasVerifiedEmail()
                    ? 'Your email address has already been verified.'
                    : 'Please confirm your email now using the separate verification message. Both email verification and Super Admin approval are required before access is granted.');
        }

        $approved = $this->status === 'active';
        $mail->subject($role.' account application '.($approved ? 'approved' : 'rejected'))
            ->line($this->applicant->name.' ('.$this->applicant->email.'): the '.$role.' account application has been '.($approved ? 'approved.' : 'rejected.'));

        if (! $applicantCopy) {
            return $mail->action('View accounts', rtrim((string) config('app.url'), '/').'/admin/users');
        }
        if (! $approved) {
            return $mail->line('For questions about this decision, please contact the editorial office.');
        }
        if (! $this->applicant->hasVerifiedEmail()) {
            return $mail->line('Please verify your email using the separate verification message before signing in.');
        }

        return $mail->action('Sign in', route($this->applicant->requested_role.'.login'));
    }
}
