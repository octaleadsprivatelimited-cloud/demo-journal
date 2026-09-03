<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ContactSubmissionReceived;
use App\Models\User;
use App\Notifications\ContactSubmissionReceivedNotification;
use Illuminate\Support\Facades\Notification;

class NotifyAdminsOfContactSubmission
{
    public function handle(ContactSubmissionReceived $event): void
    {
        $recipients = User::query()->active()
            ->whereHas('roles', fn ($query) => $query->whereIn('slug', ['super-admin', 'admin']))
            ->get();

        Notification::send($recipients, new ContactSubmissionReceivedNotification($event->submission));
    }
}
