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
            ->where(fn ($query) => $query->whereHas('roles', fn ($roles) => $roles->whereIn('slug', ['super-admin', 'admin']))->orWhere('id', $event->article->assigned_editor_id))
            ->get();

        Notification::send($recipients, new ArticleSubmittedNotification($event->article, $event->submission));
    }
}
