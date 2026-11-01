<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_unique_slugs_sanitizes_content_and_calculates_reading_time(): void
    {
        $content = '<script>alert(1)</script><h2>Evidence</h2><p onclick="steal()">'.str_repeat('word ', 440).'</p>';
        $first = Article::factory()->draft()->create(['title' => 'A Shared Headline', 'content' => $content]);
        $second = Article::factory()->draft()->create(['title' => 'A Shared Headline']);

        $this->assertSame('a-shared-headline', $first->slug);
        $this->assertSame('a-shared-headline-2', $second->slug);
        $this->assertSame(2, $first->reading_time_minutes);
        $this->assertStringNotContainsString('<script', $first->content);
        $this->assertStringNotContainsString('onclick', $first->content);
    }

    public function test_published_scope_only_returns_articles_already_available_to_readers(): void
    {
        $visible = Article::factory()->published()->create();
        Article::factory()->draft()->create();
        Article::factory()->create([
            'status' => ArticleStatus::Published,
            'published_at' => now()->addDay(),
        ]);

        $this->assertEquals([$visible->getKey()], Article::query()->published()->pluck('id')->all());
    }

    public function test_article_slugs_stay_stable_when_draft_or_published_titles_change(): void
    {
        foreach ([ArticleStatus::Draft, ArticleStatus::Published] as $status) {
            $article = Article::factory()->create(['status' => $status, 'title' => 'Original '.$status->value.' title']);
            $slug = $article->slug;

            $article->update(['title' => 'Corrected '.$status->value.' title']);

            $this->assertSame($slug, $article->fresh()->slug);
        }
    }

    public function test_other_slugged_entities_keep_their_existing_rename_behavior(): void
    {
        $category = Category::factory()->create(['name' => 'Original category']);

        $category->update(['name' => 'Renamed category']);

        $this->assertSame('renamed-category', $category->fresh()->slug);
    }
}
