<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Article;
use App\Models\ArticleVersion;
use App\Models\User;

class ArticleVersionService
{
    public function snapshot(Article $article, ?User $actor = null, ?string $changeSummary = null): ArticleVersion
    {
        $versionNumber = ((int) $article->versions()->max('version_number')) + 1;

        return $article->versions()->create([
            'created_by_id' => $actor?->getKey(),
            'version_number' => $versionNumber,
            'title' => $article->title,
            'subtitle' => $article->subtitle,
            'abstract' => $article->abstract,
            'content' => $article->content,
            'keywords' => $article->keywords,
            'references' => $article->references,
            'change_summary' => $changeSummary,
        ]);
    }
}
