<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Setting;
use App\Services\PublicSeo;
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

    public function test_verified_sjc_import_citations_keep_the_original_journal_and_publication_date_on_sjnr(): void
    {
        $this->useSjnrProfile();
        [$article, $source] = $this->copiedSjcArticle();
        Setting::put('import.sjc.article.'.$source['id'], $source, 'imports', false);

        $response = $this->get(route('articles.show', $article->slug))->assertOk()
            ->assertSee('<meta name="citation_journal_title" content="Singapore Journal of Cardiology">', false)
            ->assertSee('<meta name="citation_issn" content="2737-4025">', false)
            ->assertSee('<meta name="citation_publication_date" content="2022/06/30">', false)
            ->assertSee('<meta name="citation_doi" content="10.5281/zenodo.12345">', false)
            ->assertSee('<link rel="canonical" href="'.route('articles.show', $article->slug).'">', false)
            ->assertSee('Published in Singapore Journal of Cardiology.')
            ->assertSee('Singapore Journal of Cardiology, 2022. https://doi.org/10.5281/zenodo.12345')
            ->assertDontSee('<meta name="citation_journal_title" content="Singapore Journal of Nursing Research">', false)
            ->assertDontSee('<meta name="citation_issn" content="2737-4637">', false);
        $schema = $this->schema($response->getContent());
        $this->assertSame('Singapore Journal of Cardiology', $schema['isPartOf']['isPartOf']['isPartOf']['name']);
        $this->assertSame('2737-4025', $schema['isPartOf']['isPartOf']['isPartOf']['issn']);
        $this->assertSame('Singapore Journal of Cardiology', $schema['publisher']['name']);
        $this->assertSame('https://sjcjournal.com', $schema['publisher']['url']);
        $this->assertSame($source['source_url'], $schema['isBasedOn']);
        $this->assertStringStartsWith('2022-06-30', $schema['datePublished']);
    }

    public function test_import_like_numbers_do_not_override_the_journal_without_matching_private_provenance(): void
    {
        $this->useSjnrProfile();
        [$article, $source] = $this->copiedSjcArticle();
        $site = ['name' => 'Singapore Journal of Nursing Research', 'journal' => ['issn' => '2737-4637']];
        $this->assertSame($site['name'], PublicSeo::articleJournal($article, $site)['name']);

        foreach ([
            ['source_url' => 'https://unrelated.example/article/'.$source['slug']],
            ['id' => str_repeat('b', 20)],
            ['source_journal' => 'Another journal'],
            ['source_issn' => '9999-9999'],
            ['pdf' => ['sha256' => str_repeat('c', 64)]],
        ] as $overrides) {
            Setting::put('import.sjc.article.'.$source['id'], array_replace($source, $overrides), 'imports', false);
            $identity = PublicSeo::articleJournal($article, $site);
            $this->assertSame($site['name'], $identity['name']);
            $this->assertSame('2737-4637', $identity['issn']);
            $this->assertNull($identity['source_url']);
        }
        Setting::put('import.sjc.article.'.$source['id'], $source, 'imports', true);
        $this->assertSame($site['name'], PublicSeo::articleJournal($article, $site)['name']);
    }

    public function test_original_sjnr_articles_keep_nursing_citations(): void
    {
        $this->useSjnrProfile();
        $article = Article::factory()->published()->create(['article_number' => 'SJNR-LEGACY-8']);
        $this->get(route('articles.show', $article->slug))->assertOk()
            ->assertSee('<meta name="citation_journal_title" content="Singapore Journal of Nursing Research">', false)
            ->assertSee('<meta name="citation_issn" content="2737-4637">', false)
            ->assertDontSee('"isBasedOn"', false);
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

    public function test_homepage_describes_the_journal_topics_in_visible_copy_and_schema(): void
    {
        $response = $this->get('/')->assertOk()->assertSee('Explore open access cardiology research, clinical case reports and reviews in cardiovascular medicine.');
        $this->assertStringContainsString('cardiology research', $this->schema($response->getContent())['keywords']);
        $response->assertSee(route('policies.show', 'privacy'), false);
    }

    public function test_member_directory_pagination_and_filters_have_correct_indexing_metadata(): void
    {
        $this->get('/people?page=2')->assertOk()->assertSee('<link rel="canonical" href="'.url('/people').'?page=2">', false);
        $this->get('/people?q=heart')->assertOk()->assertSee('<meta name="robots" content="noindex, follow">', false);
    }

    public function test_account_routes_are_not_indexed_and_robots_preserves_public_author_profiles(): void
    {
        $this->get('/login')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /editor/', false)->assertSee('Disallow: /author/submissions', false)->assertDontSee('Disallow: /author/\n', false);
    }

    private function schema(string $html): array
    {
        $this->assertSame(1, preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches));

        return json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
    }

    private function useSjnrProfile(): void
    {
        $profile = config('journal_profiles.sjnr');
        config([
            'journal' => ['profile' => 'sjnr', ...$profile],
            'app.name' => $profile['name'],
            'publication.name' => $profile['name'],
            'publication.description' => $profile['description'],
            'workflow.journal' => $profile['name'],
            'workflow.issn' => $profile['issn'],
        ]);
        // Historical migrations seed SJC defaults into the isolated test DB.
        Setting::putDefined('site.name', $profile['name']);
        Setting::putDefined('journal.issn', $profile['issn']);
        Setting::putDefined('journal.eissn', null);
    }

    private function copiedSjcArticle(): array
    {
        $slug = 'copied-cardiology-publication';
        $identity = substr(hash('sha256', '/article/'.$slug), 0, 20);
        $hash = str_repeat('a', 64);
        $article = Article::factory()->published()->create([
            'title' => 'Copied cardiology publication',
            'article_number' => 'SJNR-LEGACY-SJC-'.$identity,
            'pdf_path' => 'articles/sjc-copy/'.$identity.'-'.$hash.'.pdf',
            'doi' => '10.5281/zenodo.12345',
            'published_at' => '2022-06-30 00:00:00',
            'excerpt' => 'Read the complete published article, including its figures, tables and references.',
            'abstract' => null,
        ]);

        return [$article, [
            'id' => $identity,
            'slug' => $slug,
            'source_url' => 'https://sjcjournal.com/article/'.$slug,
            'source_journal' => 'Singapore Journal of Cardiology',
            'source_issn' => '2737-4025',
            'published_date' => '2022-06-30',
            'doi' => $article->doi,
            'pdf' => ['sha256' => $hash],
        ]];
    }
}
