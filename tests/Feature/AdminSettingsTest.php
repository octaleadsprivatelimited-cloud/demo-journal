<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_only_exposes_canonical_runtime_backed_controls(): void
    {
        $response = $this->actingAs($this->settingsManager())->get(route('admin.settings.index'));

        $response
            ->assertOk()
            ->assertSee('name="settings[site.name]"', false)
            ->assertSee('name="settings[publication.comments_enabled]"', false)
            ->assertSee('Environment-managed configuration')
            ->assertDontSee('name="settings[site_name]"', false)
            ->assertDontSee('name="settings[mail_provider]"', false)
            ->assertDontSee('name="settings[login_attempt_limit]"', false)
            ->assertDontSee('name="settings[publication.reviewer_workflow_enabled]"', false);
    }

    public function test_canonical_general_and_social_settings_change_the_public_website(): void
    {
        $manager = $this->settingsManager();

        $this->actingAs($manager)->put(route('admin.settings.update'), [
            'group' => 'general',
            'settings' => [
                'site.name' => 'North Star Review',
                'site.tagline' => 'Research for a more thoughtful world.',
                'site.description' => 'Independent analysis from the North Star editorial desk.',
                'contact.email' => 'editors@north-star.test',
                'contact.phone' => '+1 555 0100',
                'contact.address' => '10 Library Square',
            ],
            // Visibility is metadata owned by the definition, not a client control.
            'public' => ['site.name' => 0],
        ])->assertRedirect()->assertSessionHas('success');

        $this->actingAs($manager)->put(route('admin.settings.update'), [
            'group' => 'social',
            'settings' => [
                'social.linkedin' => 'https://www.linkedin.com/company/north-star-review',
            ],
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame('North Star Review', Setting::value('site.name'));
        $this->assertTrue(Setting::query()->findOrFail('site.name')->is_public);

        $this->get('/')
            ->assertOk()
            ->assertSee('North Star Review')
            ->assertSee('Research for a more thoughtful world.')
            ->assertSee('https://www.linkedin.com/company/north-star-review', false);

        $this->get('/contact')
            ->assertOk()
            ->assertSee('editors@north-star.test')
            ->assertSee('+1 555 0100')
            ->assertSee('10 Library Square');
    }

    public function test_publication_switches_are_typed_and_enforced_by_public_endpoints(): void
    {
        $manager = $this->settingsManager();

        $this->actingAs($manager)->put(route('admin.settings.update'), [
            'group' => 'publication',
            'settings' => [
                'publication.comments_enabled' => '0',
                'publication.author_registration_enabled' => '0',
                'publication.pdf_downloads_enabled' => '0',
                'publication.newsletter_enabled' => '0',
            ],
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertFalse(Setting::value('publication.comments_enabled'));
        $this->assertFalse(Setting::value('publication.newsletter_enabled'));

        $article = Article::factory()->published()->create([
            'comments_enabled' => true,
            'pdf_download_enabled' => true,
            'pdf_path' => 'articles/private-source.pdf',
        ]);

        $this->post(route('comments.store', $article->slug), [
            'guest_name' => 'Reader',
            'guest_email' => 'reader@example.test',
            'body' => 'This comment should never reach persistence while discussion is disabled.',
            'comment_website' => '',
        ])->assertNotFound();

        $this->post(route('newsletter.subscribe'), [
            'name' => 'Reader',
            'email' => 'reader@example.test',
            'newsletter_website' => '',
        ])->assertNotFound();

        $this->get(route('articles.pdf', $article->slug))->assertNotFound();

        $this->post(route('logout'))->assertRedirect();
        $this->get(route('author.register'))->assertNotFound();
    }

    public function test_aliases_unimplemented_controls_and_unsafe_social_urls_are_rejected(): void
    {
        $manager = $this->settingsManager();

        $this->actingAs($manager)
            ->from(route('admin.settings.index'))
            ->put(route('admin.settings.update'), [
                'group' => 'publication',
                'settings' => ['enable_comments' => '0'],
            ])
            ->assertRedirect(route('admin.settings.index'))
            ->assertSessionHasErrors('settings');

        $this->assertDatabaseMissing('settings', ['key' => 'enable_comments']);

        $this->actingAs($manager)
            ->from(route('admin.settings.index'))
            ->put(route('admin.settings.update'), [
                'group' => 'social',
                'settings' => ['social.x' => 'javascript:alert(1)'],
            ])
            ->assertRedirect(route('admin.settings.index'))
            ->assertSessionHasErrors('settings');

        $this->assertDatabaseMissing('settings', ['key' => 'social.x']);
    }

    public function test_setting_seeder_uses_definitions_without_overwriting_saved_values(): void
    {
        $this->seed(SettingSeeder::class);
        Setting::putDefined('site.name', 'Saved Editorial Name');

        $this->seed(SettingSeeder::class);

        $this->assertSame('Saved Editorial Name', Setting::value('site.name'));
        $this->assertDatabaseHas('settings', ['key' => 'site.tagline', 'group' => 'general', 'is_public' => true]);
        $this->assertDatabaseMissing('settings', ['key' => 'publication.reviewer_workflow_enabled']);
    }

    private function settingsManager(): User
    {
        $role = Role::query()->firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'is_system' => true],
        );
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
