<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\WorkflowMail;
use App\Models\NotificationDelivery;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

final class MicrosoftEmailService
{
    public function send(NotificationDelivery $delivery): void
    {
        if (! config('services.microsoft_email.enabled')) {
            throw new RuntimeException('Microsoft email delivery is not configured.');
        }

        Mail::mailer((string) config('services.microsoft_email.mailer', 'smtp'))
            ->to($delivery->recipient_email)
            ->send(new WorkflowMail($delivery));
    }
}
