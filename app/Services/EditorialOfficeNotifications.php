<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification as Notifications;

final class EditorialOfficeNotifications
{
    public function send(Collection $recipients, Notification $notification, string $inbox, array $alreadyMailed = []): void
    {
        Notifications::send($recipients, $notification);

        $address = trim((string) config('publication.'.$inbox));
        if (! filter_var($address, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        // A staff account using the office mailbox already receives this email.
        if ($recipients->contains(fn ($user): bool => strcasecmp($user->email, $address) === 0)
            || in_array(strtolower($address), array_map('strtolower', $alreadyMailed), true)) {
            return;
        }

        Notifications::route('mail', $address)->notify(clone $notification);
    }
}
