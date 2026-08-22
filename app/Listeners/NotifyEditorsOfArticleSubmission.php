<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ArticleSubmitted;
use App\Models\User;
use App\Notifications\ArticleSubmittedNotification;
use Illuminate\Support\Facades\Notification;

class NotifyEditorsOfArticleSubmission
{
    public function handle(ArticleSubmitted $event): void
    {
        $recipients = User::query()->active()
            ->whereHas('roles', fn ($query) => $query->whereIn('slug', ['super-admin', 'admin', 'editor']))
            ->get();

        Notification::send($recipients, new ArticleSubmittedNotification($event->article, $event->submission));
    }
}
