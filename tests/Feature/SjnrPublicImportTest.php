<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Author;
use App\Models\EditorialMember;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class SjnrPublicImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_archive_import_preserves_source_credit_pdfs_and_about_copy_without_accounts_or_mail(): void
    {
        $this->useNursingPreview();
        Mail::fake();
        Queue::fake();
        Storage::fake('local');
        Storage::fake('public');
        $creator = User::factory()->create(['name' => 'SJNR Archive Migration', 'is_active' => false, 'email_verified_at' => null]);
        $directory = $this->fixture();

        try {
            $this->artisan('journal:import-sjnr-public', ['directory' => $directory])->assertSuccessful();
            $this->assertDatabaseCount('articles', 0);
            $this->artisan('journal:import-sjnr-public', ['directory' => $directory, '--creator' => $creator->getKey(), '--apply' => true])->assertSuccessful();

            $article = Article::query()->firstOrFail();
            $this->assertSame('SJNR-LEGACY-42', $article->article_number);
            $this->assertSame('source-nursing-paper', $article->slug);
            $this->assertSame('published', $article->status->value);
            $this->assertSame('2021-01-04', $article->published_at->toDateString());
            $this->assertSame('10.46978/sjnr.example', $article->doi);
            $this->assertSame(['Research Author', 'Second Author'], $article->authors->pluck('name')->all());
            $this->assertSame(2, Author::query()->count());
            $this->assertSame(1, EditorialMember::query()->count());
            $this->assertDatabaseCount('users', 1);
            $this->assertFalse($creator->fresh()->is_active);
            $this->assertNull($creator->fresh()->email_verified_at);
            Storage::disk('local')->assertExists($article->pdf_path);
            $this->get(route('articles.pdf', $article->slug))->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $this->get('/about')->assertOk()->assertSee('The original SJNR nursing mission is preserved.')
                ->assertDontSee('Ideas with enough room to become useful.');
            $this->get('/policies/author-guidelines')->assertOk()->assertSee('notifications@sjnrjournal.com')
                ->assertDontSee('submit@larixjournals.com');
            $mapping = Setting::value('import.sjnr.legacy_routes');
            $this->assertSame(route('articles.show', $article->slug, false), $mapping['/journals/42/source-nursing-paper/abstract']);
            $this->assertSame(route('archive.issue', $article->journal_issue_id, false), $mapping['/archives/2021/281/Issue 1']);
            Mail::assertNothingOutgoing();
            Queue::assertNothingPushed();
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_reimport_preserves_later_article_pdf_editorial_and_page_edits(): void
    {
        $this->useNursingPreview();
        Storage::fake('local');
        Storage::fake('public');
        $creator = User::factory()->create(['is_active' => false]);
        $directory = $this->fixture();
        $arguments = ['directory' => $directory, '--creator' => $creator->getKey(), '--apply' => true];

        try {
            $this->artisan('journal:import-sjnr-public', $arguments)->assertSuccessful();
            $article = Article::query()->firstOrFail();
            Storage::disk('local')->put('articles/editorial-replacement.pdf', '%PDF-1.4 replacement');
            $article->update(['title' => 'Later approved editorial title', 'pdf_path' => 'articles/editorial-replacement.pdf']);
            EditorialMember::query()->firstOrFail()->update(['name' => 'An approved later display name']);
            Setting::put('journal_page.about', ['content' => 'Approved revised nursing mission.', 'draft' => 'Approved revised nursing mission.', 'published' => true, 'version' => 2], 'journal_pages', false);

            $this->artisan('journal:import-sjnr-public', $arguments)->assertSuccessful();

            $this->assertDatabaseCount('articles', 1);
            $this->assertDatabaseCount('authors', 2);
            $this->assertDatabaseCount('editorial_members', 1);
            $this->assertSame('Later approved editorial title', $article->fresh()->title);
            $this->assertSame('articles/editorial-replacement.pdf', $article->fresh()->pdf_path);
            $this->assertSame('An approved later display name', EditorialMember::query()->firstOrFail()->name);
            $this->get('/about')->assertOk()->assertSee('Approved revised nursing mission.');
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_long_source_aliases_use_the_persisted_canonical_slug_and_follow_later_editorial_changes(): void
    {
        $this->useNursingPreview();
        Storage::fake('local');
        Storage::fake('public');
        $creator = User::factory()->create(['is_active' => false]);
        $directory = $this->fixture();
        $sourceSlug = str_repeat('nursing-', 28).'evidence';
        $sourcePath = '/journals/42/'.$sourceSlug.'/abstract';
        $paperPath = '/storage/live/source.pdf';
        $data = json_decode(file_get_contents($directory.'/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
        $data['articles'][0]['title'] = str_replace('-', ' ', $sourceSlug);
        $data['articles'][0]['slug'] = $sourceSlug;
        $data['articles'][0]['source_url'] = 'https://sjnrjournal.com'.$sourcePath;
        $data['legacy_routes'][0] = ['source_path' => $sourcePath, 'route' => 'articles.show', 'parameters' => ['slug' => $sourceSlug]];
        $data['legacy_routes'][] = ['source_path' => $paperPath, 'route' => 'articles.pdf', 'parameters' => ['slug' => $sourceSlug]];
        file_put_contents($directory.'/manifest.json', json_encode($data, JSON_THROW_ON_ERROR));
        $arguments = ['directory' => $directory, '--creator' => $creator->getKey(), '--apply' => true];

        try {
            $this->artisan('journal:import-sjnr-public', $arguments)->assertSuccessful();
            $article = Article::query()->firstOrFail();
            $this->assertLessThan(strlen($sourceSlug), strlen($article->slug));
            $mapping = Setting::value('import.sjnr.legacy_routes');
            $this->assertSame(route('articles.show', $article->slug, false), $mapping[$sourcePath]);
            $this->assertSame(route('articles.pdf', $article->slug, false), $mapping[$paperPath]);
            $this->get($mapping[$sourcePath])->assertOk();
            $this->get($mapping[$paperPath])->assertOk()->assertHeader('Content-Type', 'application/pdf');

            $article->update(['slug' => 'editorial-reviewed-nursing-url', 'title' => 'An approved local title']);
            $original = $article->fresh()->getRawOriginal();
            $this->artisan('journal:import-sjnr-public', $arguments)->assertSuccessful();

            $this->assertSame($original, $article->fresh()->getRawOriginal());
            $this->assertDatabaseCount('articles', 1);
            $mapping = Setting::value('import.sjnr.legacy_routes');
            $this->assertSame(route('articles.show', 'editorial-reviewed-nursing-url', false), $mapping[$sourcePath]);
            $this->assertSame(route('articles.pdf', 'editorial-reviewed-nursing-url', false), $mapping[$paperPath]);
            $this->get($mapping[$sourcePath])->assertOk();
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_invalid_source_pdf_is_rejected_before_any_archive_rows_are_written(): void
    {
        $this->useNursingPreview();
        $creator = User::factory()->create(['is_active' => false]);
        $directory = $this->fixture();
        file_put_contents($directory.'/pdfs/42.pdf', '%PDF-1.4 altered after collection');

        try {
            $this->artisan('journal:import-sjnr-public', ['directory' => $directory, '--creator' => $creator->getKey(), '--apply' => true])->assertFailed();
            $this->assertDatabaseCount('articles', 0);
            $this->assertDatabaseCount('authors', 0);
            $this->assertDatabaseCount('editorial_members', 0);
            $this->assertDatabaseMissing('settings', ['key' => 'journal_page.about']);
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_import_rejects_wrong_profile_and_unrelated_editorial_records(): void
    {
        $directory = $this->fixture();
        try {
            $this->artisan('journal:import-sjnr-public', ['directory' => $directory, '--apply' => true])->assertFailed();
            $this->useNursingPreview();
            $creator = User::factory()->create(['is_active' => false]);
            $existing = Article::factory()->create(['title' => 'An unrelated existing manuscript']);
            $this->artisan('journal:import-sjnr-public', ['directory' => $directory, '--creator' => $creator->getKey(), '--apply' => true])->assertFailed();
            $this->assertDatabaseCount('articles', 1);
            $this->assertSame('An unrelated existing manuscript', $existing->fresh()->title);
            $this->assertDatabaseCount('editorial_members', 0);
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_import_requires_a_separately_initialized_preview_and_an_explicit_creator(): void
    {
        $this->useNursingPreview();
        Setting::query()->whereKey('deployment.sjnr_initialized_at')->delete();
        $directory = $this->fixture();
        try {
            $this->artisan('journal:import-sjnr-public', ['directory' => $directory, '--apply' => true])->assertFailed();
            Setting::put('deployment.sjnr_initialized_at', now()->toISOString(), 'deployment', false);
            $this->artisan('journal:import-sjnr-public', ['directory' => $directory, '--apply' => true])->assertFailed();
            $this->assertDatabaseCount('users', 0);
            $this->assertDatabaseCount('articles', 0);
        } finally {
            File::deleteDirectory($directory);
        }
    }

    private function useNursingPreview(): void
    {
        $profile = config('journal_profiles.sjnr');
        config([
            'journal' => ['profile' => 'sjnr', ...$profile],
            'app.name' => $profile['name'],
            'app.url' => 'https://sjnr-example.hostingersite.com',
            'publication.name' => $profile['name'],
            'publication.description' => $profile['description'],
            'publication.contact_email' => 'notifications@sjnrjournal.com',
            'publication.indexing_enabled' => false,
        ]);
        Setting::put('deployment.sjnr_initialized_at', now()->toISOString(), 'deployment', false);
    }

    private function fixture(): string
    {
        $directory = sys_get_temp_dir().'/sjnr-public-test-'.bin2hex(random_bytes(8));
        mkdir($directory.'/pdfs', 0700, true);
        $pdf = "%PDF-1.4\nPublic archive fixture\n%%EOF\n";
        file_put_contents($directory.'/pdfs/42.pdf', $pdf);
        $data = [
            'schema_version' => 1, 'source' => 'https://sjnrjournal.com', 'errors' => [], 'warnings' => [],
            'articles' => [[
                'id' => '42', 'source_url' => 'https://sjnrjournal.com/journals/42/source-nursing-paper/abstract',
                'slug' => 'source-nursing-paper', 'title' => 'Source nursing paper',
                'authors' => ['Research Author', 'Second Author', 'Research Author'],
                'doi' => '10.46978/sjnr.example', 'published_date' => '2021-01-04',
                'volume' => '2', 'issue' => '1', 'abstract' => 'An abstract retained from the original public nursing journal.',
                'keywords' => ['Nursing', 'Patient care'],
                'pdf' => ['source_url' => 'https://sjnrjournal.com/storage/live/source.pdf', 'file' => 'pdfs/42.pdf', 'sha256' => hash('sha256', $pdf)],
            ]],
            'issues' => [['source_id' => '281', 'source_url' => 'https://sjnrjournal.com/archives/2021/281/Issue%201', 'year' => 2021, 'label' => 'Issue 1', 'article_ids' => ['42']]],
            'editorial_members' => [['name' => 'Source Nursing Editor', 'role' => 'Editor-in-Chief', 'institution' => 'Source University', 'source_url' => 'https://sjnrjournal.com/chief-editors/9/source-editor', 'photo' => null]],
            'pages' => [
                ['slug' => 'about', 'title' => 'About the Journal', 'source_url' => 'https://sjnrjournal.com/aboutus', 'content' => 'The original SJNR nursing mission is preserved.', 'published' => true],
                ['slug' => 'author-guidelines', 'title' => 'Author Guidelines', 'source_url' => 'https://sjnrjournal.com/author-guidelines', 'content' => 'Contact submit@larixjournals.com for author guidance.', 'published' => true],
            ],
            'media' => [], 'resources' => [],
            'legacy_routes' => [
                ['source_path' => '/journals/42/source-nursing-paper/abstract', 'route' => 'articles.show', 'parameters' => ['slug' => 'source-nursing-paper']],
                ['source_path' => '/archives/2021/281/Issue 1', 'route' => 'archive.issue', 'source_issue_id' => '281', 'article_ids' => ['42']],
            ],
        ];
        file_put_contents($directory.'/manifest.json', json_encode($data, JSON_THROW_ON_ERROR));

        return $directory;
    }
}
