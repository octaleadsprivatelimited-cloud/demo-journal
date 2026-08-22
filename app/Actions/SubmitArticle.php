<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Article;
use App\Models\Submission;
use App\Models\User;
use App\Services\ArticleWorkflowService;

class SubmitArticle
{
    public function __construct(private readonly ArticleWorkflowService $workflow) {}

    public function execute(Article $article, User $actor, ?string $coverLetter = null): Submission
    {
        return $this->workflow->submit($article, $actor, $coverLetter);
    }
}
