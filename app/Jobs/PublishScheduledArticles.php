<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Services\ArticleWorkflowService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class PublishScheduledArticles implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function handle(ArticleWorkflowService $workflow): void
    {
        Article::query()
            ->status(ArticleStatus::Scheduled)
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($articles) use ($workflow): void {
                foreach ($articles as $article) {
                    try {
                        $workflow->transition($article, ArticleStatus::Published, note: 'Published automatically at the scheduled time.');
                    } catch (Throwable $exception) {
                        Log::error('Scheduled article publication failed.', [
                            'article_id' => $article->getKey(),
                            'exception' => $exception,
                        ]);
                    }
                }
            });
    }
}
