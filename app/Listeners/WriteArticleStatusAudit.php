<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ArticleStatusChanged;
use App\Services\AuditService;

class WriteArticleStatusAudit
{
    public function __construct(private readonly AuditService $audit) {}

    public function handle(ArticleStatusChanged $event): void
    {
        $this->audit->record(
            $event->article,
            'status_changed',
            ['status' => $event->from->value],
            ['status' => $event->to->value],
            $event->note,
            $event->actor,
        );
    }
}
