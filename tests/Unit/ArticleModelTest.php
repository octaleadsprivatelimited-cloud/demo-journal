<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\ArticleStatus;
use App\Models\Article;
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
}
