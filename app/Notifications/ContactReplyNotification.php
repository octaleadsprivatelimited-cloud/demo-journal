<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ContactReplyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $subject, private readonly string $body, private readonly string $senderName)
    {
        $this->onQueue('mail');
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->subject)->greeting('Hello,')->line('The editorial team has replied to your enquiry:');
        foreach (preg_split('/\r\n|\r|\n/', $this->body) ?: [] as $line) {
            if (filled($line)) {
                $mail->line($line);
            }
        }

        return $mail->salutation("Regards,\n{$this->senderName}\n".config('app.name'));
    }
}
