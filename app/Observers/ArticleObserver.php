<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Article;
use App\Services\RichTextSanitizer;
use Illuminate\Support\Str;

class ArticleObserver
{
    public function __construct(private readonly RichTextSanitizer $sanitizer) {}

    public function creating(Article $article): void
    {
        $article->public_id ??= (string) Str::uuid();
    }

    public function saving(Article $article): void
    {
        if ($article->isDirty('content')) {
            $article->content = $this->sanitizer->sanitize((string) $article->content);
        }

        if ($article->isDirty('content') || ! $article->reading_time_minutes) {
            $plainText = html_entity_decode(strip_tags((string) $article->content));
            $words = str_word_count($plainText);
            $article->reading_time_minutes = max(1, (int) ceil($words / 220));
        }

        if (! $article->excerpt && $article->content) {
            $article->excerpt = Str::words(trim(strip_tags((string) $article->content)), 36);
        }
    }
}
