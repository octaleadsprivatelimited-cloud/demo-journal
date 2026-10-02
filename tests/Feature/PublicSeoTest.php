<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PublicSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_imported_articles_have_distinct_factual_descriptions_instead_of_the_shared_placeholder(): void
    {
        foreach (['Anaphylaxis diagnosis and management', 'Dilated cardiomyopathy in sepsis'] as $title) {
            $article = Article::factory()->published()->create([
                'title' => $title, 'excerpt' => 'Read the complete published article, including its figures, tables and references.', 'abstract' => null,
            ]);
            $response = $this->get(route('articles.show', $article->slug))->assertOk();
            $response->assertSee('<meta name="description" content="'.$title.'. Published in Singapore Journal of Cardiology.', false);
            $response->assertDontSee('<meta name="description" content="Read the complete published article', false);
        }
    }

    public function test_journal_citations_and_editorial_focus_keywords_are_exposed_in_article_metadata(): void
    {
        $article = Article::factory()->published()->create(['keywords' => ['heart failure']]);
        $article->seoMetadata()->create(['focus_keywords' => ['cardiac rehabilitation', 'heart failure'], 'twitter_card' => 'summary']);
        $response = $this->get(route('articles.show', $article->slug))->assertOk();
        $response->assertSee('<meta name="citation_journal_title" content="Singapore Journal of Cardiology">', false)
            ->assertSee('<meta name="twitter:card" content="summary">', false);
        $schema = $this->schema($response->getContent());
        $this->assertSame('heart failure, cardiac rehabilitation', $schema['keywords']);
        $this->assertSame('Singapore Journal of Cardiology', $schema['isPartOf']['isPartOf']['isPartOf']['name']);
    }

    public function test_paginated_lists_have_their_own_canonicals_without_tracking_parameters(): void
    {
        Article::factory()->published()->count(14)->create();
        foreach (['articles.index', 'journals.index'] as $route) {
            $this->get(route($route, ['page' => 2, 'utm_source' => 'newsletter']))->assertOk()
                ->assertSee('<link rel="canonical" href="'.route($route, ['page' => 2]).'">', false)
                ->assertSee('Page 2')
                ->assertSee('<meta name="robots" content="index, follow, max-image-preview:large">', false);
        }
        $this->get(route('articles.index', ['page' => 1]))->assertSee('<link rel="canonical" href="'.route('articles.index').'">', false);
    }

    public function test_internal_filtered_results_are_not_indexed(): void
    {
        $this->get(route('articles.index', ['q' => 'heart']))->assertOk()
            ->assertSee('<meta name="robots" content="noindex, follow">', false);
        $this->get(route('search', ['q' => 'heart']))->assertOk()
            ->assertSee('<meta name="robots" content="noindex, follow">', false);
    }

    public function test_schema_is_valid_json_even_when_an_article_title_contains_html_delimiters(): void
    {
        $article = Article::factory()->published()->create(['title' => 'Research </script><script>untrusted()</script>', 'excerpt' => '<p>Heart &amp; vascular research.</p>']);
        $response = $this->get(route('articles.show', $article->slug))->assertOk();
        $schema = $this->schema($response->getContent());
        $this->assertSame($article->title, $schema['headline']);
        $this->assertSame('Heart & vascular research.', $schema['description']);
        $response->assertSee('<meta name="description" content="Heart &amp; vascular research.">', false);
    }

    public function test_sitemap_includes_journal_and_author_resources_but_excludes_drafts(): void
    {
        $draft = Article::factory()->draft()->create();
        $this->get(route('sitemap'))->assertOk()
            ->assertSee(config('publication.sitemap_base_url').'/journals', false)
            ->assertSee(config('publication.sitemap_base_url').'/resources', false)
            ->assertDontSee($draft->slug);
    }

    public function test_search_console_verification_uses_the_configured_value_and_social_images_use_png(): void
    {
        config()->set('publication.integrations.search_console_verification', 'journal-verification-test');
        $this->get(route('home'))->assertOk()
            ->assertSee('<meta name="google-site-verification" content="journal-verification-test">', false)
            ->assertSee('assets/larix-logo-transparent.png', false)
            ->assertSee('Cardiology Research');
    }

    private function schema(string $html): array
    {
        $this->assertSame(1, preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches));

        return json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
    }
}
