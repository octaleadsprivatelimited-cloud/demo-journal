<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\NotificationDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;

class WorkflowMail extends Mailable
{
    use Queueable;

    public function __construct(public readonly NotificationDelivery $delivery) {}

    public function build(): self
    {
        $mail = $this->subject($this->delivery->subject)
            ->from((string) config('services.microsoft_email.sender'), (string) config('services.microsoft_email.sender_name'))
            ->view('emails.workflow', ['delivery' => $this->delivery, 'payload' => $this->delivery->payload ?? []]);

        if (filled(config('services.microsoft_email.reply_to'))) {
            $mail->replyTo((string) config('services.microsoft_email.reply_to'));
        }

        return $mail;
    }
}
