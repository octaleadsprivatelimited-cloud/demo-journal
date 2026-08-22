<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\User;
use App\Services\ArticleWorkflowService;
use Carbon\CarbonInterface;

class TransitionArticleStatus
{
    public function __construct(private readonly ArticleWorkflowService $workflow) {}

    public function execute(
        Article $article,
        ArticleStatus $status,
        ?User $actor = null,
        ?string $note = null,
        ?CarbonInterface $scheduledFor = null,
    ): Article {
        return $this->workflow->transition($article, $status, $actor, $note, $scheduledFor);
    }
}
