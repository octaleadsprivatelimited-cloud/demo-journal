<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ArticleStatusChanged;
use App\Notifications\ArticleStatusChangedNotification;

class NotifyAuthorOfArticleStatus
{
    public function handle(ArticleStatusChanged $event): void
    {
        $creator = $event->article->creator()->first();

        $creator?->notify(new ArticleStatusChangedNotification($event->article, $event->to, $event->note));
    }
}
