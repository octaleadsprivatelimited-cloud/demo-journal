<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Setting;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class NursingJournalInitializationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sjnr_initialization_replaces_only_fresh_install_identity_and_legacy_contacts(): void
    {
        $this->selectNursingProfile();
        $this->seed(SettingSeeder::class);

        $this->artisan('journal:initialize-sjnr', ['--apply' => true, '--email' => 'notifications@sjnrjournal.com'])
            ->assertSuccessful();

        $this->assertSame('Singapore Journal of Nursing Research', Setting::value('site.name'));
        $this->assertSame('2737-4637', Setting::value('journal.issn'));
        $this->assertSame('notifications@sjnrjournal.com', Setting::value('contact.email'));
        $this->assertSame('notifications@sjnrjournal.com', Setting::value('contact.editorial_email'));
        $this->assertNull(Setting::value('contact.additional_email'));
        $this->assertDatabaseHas('categories', ['slug' => 'nursing-research']);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('articles', 0);

        Setting::putDefined('site.name', 'Approved SJNR editorial name');
        $this->artisan('journal:initialize-sjnr', ['--apply' => true, '--email' => 'notifications@sjnrjournal.com'])
            ->assertFailed();
        $this->assertSame('Approved SJNR editorial name', Setting::value('site.name'));
    }

    public function test_initialization_refuses_the_sjc_profile_and_non_sjnr_mailboxes(): void
    {
        $this->artisan('journal:initialize-sjnr', ['--apply' => true, '--email' => 'notifications@sjnrjournal.com'])
            ->assertFailed();
        $this->selectNursingProfile();
        $this->artisan('journal:initialize-sjnr', ['--apply' => true, '--email' => 'editor@gmail.com'])
            ->assertFailed();
        $this->assertDatabaseMissing('settings', ['key' => 'deployment.sjnr_initialized_at']);
    }

    public function test_initialization_refuses_to_overwrite_a_database_with_articles(): void
    {
        $this->selectNursingProfile();
        Article::factory()->create(['title' => 'An existing editorial record']);
        Setting::putDefined('site.name', 'Existing journal');

        $this->artisan('journal:initialize-sjnr', ['--apply' => true, '--email' => 'notifications@sjnrjournal.com'])
            ->assertFailed();

        $this->assertSame('Existing journal', Setting::value('site.name'));
        $this->assertDatabaseHas('articles', ['title' => 'An existing editorial record']);
    }

    private function selectNursingProfile(): void
    {
        $journal = config('journal_profiles.sjnr');
        config([
            'journal' => ['profile' => 'sjnr', ...$journal],
            'publication.name' => $journal['name'],
            'publication.description' => $journal['description'],
            'publication.tagline' => $journal['tagline'],
        ]);
    }
}
