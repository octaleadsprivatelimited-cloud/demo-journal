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

    public function updated(Article $article): void
    {
        foreach (['pdf_path' => 'local', 'featured_image_path' => 'public'] as $field => $disk) {
            $old = $article->getRawOriginal($field);
            $new = $article->$field;
            if (! $article->wasChanged($field) || ! $old || ! $new) {
                continue;
            }
            \Illuminate\Support\Facades\DB::afterCommit(function () use ($old, $new, $disk): void {
                $storage = \Illuminate\Support\Facades\Storage::disk($disk);
                if ($disk === 'local') {
                    \App\Models\WorkflowFile::where('path', $old)->update([
                        'path' => $new, 'original_name' => basename($new),
                        'size' => $storage->size($new), 'checksum' => hash('sha256', $storage->get($new)),
                    ]);
                }
                if ($storage->exists($old) && ! $storage->delete($old)) {
                    throw new \RuntimeException('The previous upload could not be removed from storage.');
                }
            });
        }
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
