<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Article;
use App\Models\Review;
use App\Models\User;
use App\Services\ArticleWorkflowService;
use Carbon\CarbonInterface;

class AssignReviewer
{
    public function __construct(private readonly ArticleWorkflowService $workflow) {}

    public function execute(Article $article, User $reviewer, ?User $assignedBy = null, ?CarbonInterface $dueAt = null): Review
    {
        return $this->workflow->assignReviewer($article, $reviewer, $assignedBy, $dueAt);
    }
}
