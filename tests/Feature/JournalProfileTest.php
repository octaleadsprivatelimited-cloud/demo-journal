<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Setting;
use App\Services\PublicationSettings;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class JournalProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_sjnr_profile_supplies_nursing_identity_copy_keywords_and_metadata(): void
    {
        $this->useSjnrProfile();
        $this->seed(SettingSeeder::class);

        $this->assertSame('Singapore Journal of Nursing Research', Setting::value('site.name'));
        $this->assertSame('SJNR', Setting::value('journal.abbreviation'));
        $this->assertSame('2737-4637', Setting::value('journal.issn'));

        $response = $this->get('/')->assertOk()
            ->assertSee('<h1 class="home-title">Singapore Journal of Nursing Research</h1>', false)
            ->assertSee('Nursing research &amp; clinical practice', false)
            ->assertSee('Explore open access nursing research, clinical studies and reviews in nursing and healthcare.')
            ->assertDontSee('Singapore Journal of Cardiology')
            ->assertDontSee('cardiology research');

        $this->assertSame(1, preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $response->getContent(), $matches));
        $schema = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('Singapore Journal of Nursing Research', $schema['name']);
        $this->assertStringContainsString('nursing research', $schema['keywords']);

        foreach (['/journals', '/articles', '/archive', '/downloads', '/resources', '/register'] as $path) {
            $this->get($path)->assertOk()->assertDontSee('Singapore Journal of Cardiology');
        }

        $this->get('/resources')->assertSee('Submission resources are being prepared.')
            ->assertDontSee('/author-resources/copyright-form.pdf', false);
    }

    public function test_profile_defaults_do_not_overwrite_administrator_settings(): void
    {
        $this->useSjnrProfile();
        Setting::putDefined('site.name', 'Approved Nursing Journal Name');
        Setting::putDefined('site.description', 'A description approved by the editorial office.');
        Setting::putDefined('journal.subject_areas', 'Approved nursing subject areas');

        $this->seed(SettingSeeder::class);

        $site = app(PublicationSettings::class)->site();
        $this->assertSame('Approved Nursing Journal Name', $site['name']);
        $this->assertSame('A description approved by the editorial office.', $site['description']);
        $this->assertSame('Approved nursing subject areas', $site['journal']['subject_areas']);
        $this->get('/')->assertOk()->assertSee('<h1 class="home-title">Approved Nursing Journal Name</h1>', false);
        $this->get('/login')->assertOk()->assertSee('Approved Nursing Journal Name');
    }

    public function test_nursing_resource_links_appear_only_after_the_source_forms_are_present(): void
    {
        $this->useSjnrProfile();
        Storage::fake('public');

        $this->get('/resources')->assertOk()->assertSee('Submission resources are being prepared.');
        Storage::disk('public')->put('imports/sjnr/resources/copyright-form.pdf', '%PDF-1.4 source form fixture');

        $this->get('/resources')->assertOk()
            ->assertSee('/storage/imports/sjnr/resources/copyright-form.pdf', false)
            ->assertSee('SJNR Copyright Transfer and Authorization Form.pdf')
            ->assertDontSee('/storage/imports/sjnr/resources/cover-letter.pdf', false)
            ->assertDontSee('/author-resources/copyright-form.pdf', false);
    }

    public function test_sjnr_article_citations_and_reading_pdf_use_the_selected_journal(): void
    {
        $this->useSjnrProfile();
        $this->seed(SettingSeeder::class);
        $article = Article::factory()->published()->create([
            'title' => 'Nursing education research', 'pdf_download_enabled' => true,
        ]);

        $this->get(route('articles.show', $article->slug))->assertOk()
            ->assertSee('<meta name="citation_journal_title" content="Singapore Journal of Nursing Research">', false)
            ->assertSee('<meta name="citation_issn" content="2737-4637">', false)
            ->assertDontSee('Singapore Journal of Cardiology');

        $this->get(route('articles.pdf', $article->slug))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertSee('Singapore Journal of Nursing Research', false)
            ->assertDontSee('Singapore Journal of Cardiology', false);
    }

    public function test_imported_sjnr_articles_preserve_the_legacy_pdf_print_flow(): void
    {
        $this->useSjnrProfile();
        $article = Article::factory()->published()->create([
            'article_number' => 'SJNR-LEGACY-17',
            'pdf_path' => 'articles/published-source.pdf',
            'pdf_download_enabled' => true,
        ]);

        $this->get(route('articles.print', $article->slug))
            ->assertRedirect(route('articles.pdf', ['slug' => $article->slug, 'inline' => 1]));
        $this->get(route('articles.show', $article->slug))->assertOk()
            ->assertDontSee('name="citation_firstpage"', false)
            ->assertSee('id="full-paper"', false);
    }

    public function test_staging_switch_overrides_page_robots_and_blocks_sitemap_discovery(): void
    {
        config(['publication.indexing_enabled' => false]);

        foreach (['/', '/search', '/articles', '/policies/author-guidelines'] as $path) {
            $this->get($path)->assertOk()
                ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
                ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        }

        $this->get('/robots.txt')->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertContent("User-agent: *\nDisallow: /\n")
            ->assertDontSee('Sitemap:');
        $this->get('/sitemap.xml')->assertNotFound()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/')->assertDontSee('href="'.route('sitemap').'"', false);
    }

    public function test_indexing_remains_enabled_for_the_default_sjc_profile(): void
    {
        $this->assertSame('sjc', config('journal.profile'));
        $this->assertTrue(config('publication.indexing_enabled'));
        $this->get('/')->assertOk()->assertSee('Cardiology Research')
            ->assertSee('<meta name="robots" content="index, follow, max-image-preview:large">', false)
            ->assertHeaderMissing('X-Robots-Tag');
        $this->get('/sitemap.xml')->assertOk();
    }

    public function test_imported_resources_replace_profile_downloads_in_the_page_and_navigation(): void
    {
        $this->useSjnrProfile();
        Storage::fake('public');
        Storage::disk('public')->put('imports/sjc-pages/guidelines.pdf', '%PDF-1.4');
        Setting::put('journal.resources', [
            ['Copied guidelines', 'guidelines.pdf', '/storage/imports/sjc-pages/guidelines.pdf', 'Imported author guidance.', 'imports/sjc-pages/guidelines.pdf'],
            ['Unavailable form', 'missing.pdf', '/storage/missing.pdf', 'Not yet imported.', 'missing.pdf'],
        ], 'publication', false);

        $this->get('/resources')->assertOk()->assertSee('Copied guidelines')
            ->assertSee('/storage/imports/sjc-pages/guidelines.pdf', false)->assertDontSee('Unavailable form');
        $this->get('/')->assertOk()->assertSee('/storage/imports/sjc-pages/guidelines.pdf', false);
    }

    private function useSjnrProfile(): void
    {
        $profile = config('journal_profiles.sjnr');
        config([
            'journal' => ['profile' => 'sjnr', ...$profile],
            'app.name' => $profile['name'],
            'publication.name' => $profile['name'],
            'publication.description' => $profile['description'],
            'publication.tagline' => $profile['tagline'],
            'publication.seo_keywords' => $profile['seo_keywords'],
            'workflow.journal' => $profile['name'],
            'workflow.issn' => $profile['issn'],
        ]);

        // Only the disposable test database is reset to model a new journal setup.
        Setting::query()->delete();
    }
}
