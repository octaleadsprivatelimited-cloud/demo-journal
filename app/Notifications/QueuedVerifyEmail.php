<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\URL;

final class QueuedVerifyEmail extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue('mail')->afterCommit();
    }

    protected function verificationUrl($notifiable)
    {
        if ($notifiable instanceof User && $notifiable->isPendingApproval()) {
            return URL::temporarySignedRoute(
                'registration.verification.verify',
                now()->addMinutes((int) config('auth.verification.expire', 60)),
                ['id' => $notifiable->getKey(), 'hash' => sha1($notifiable->getEmailForVerification())],
            );
        }

        return parent::verificationUrl($notifiable);
    }
}
