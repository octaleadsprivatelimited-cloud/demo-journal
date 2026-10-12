<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\EditorialMember;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\QueuedVerifyEmail;
use App\Services\CardiologyHomepageSetup;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class HomepagePresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_selections_exclude_unpublished_records_and_active_empty_disciplines_are_discoverable(): void
    {
        $selected = Article::factory()->published()->create(['is_featured' => true]);
        Article::factory()->draft()->create(['is_featured' => true, 'title' => 'Private featured draft']);
        Article::factory()->published()->create(['is_featured' => true, 'published_at' => now()->addDay(), 'title' => 'Future featured article']);
        $discipline = Category::factory()->create(['name' => 'Preventive Cardiology', 'parent_id' => null, 'is_active' => true]);
        $hidden = Category::factory()->create(['name' => 'Hidden discipline', 'is_active' => false]);

        $this->get('/')->assertOk()->assertSee('Featured research')->assertSee('perspectives-grid')
            ->assertViewHas('editorSelections', fn ($articles) => $articles->modelKeys() === [$selected->id])
            ->assertViewHas('categories', fn ($categories) => $categories->contains('id', $discipline->id) && ! $categories->contains('id', $hidden->id))
            ->assertDontSee('Private featured draft')->assertDontSee('Future featured article');
        $this->get(route('categories.show', $discipline->slug))->assertOk();
        $this->get(route('articles.index'))->assertOk()->assertSee('publication-grid')->assertSee('publication-container');
    }

    public function test_all_active_chief_editors_have_homepage_controls_and_board_profiles(): void
    {
        foreach (['First chief', 'Second chief', 'Third chief'] as $index => $name) {
            EditorialMember::create(['name' => $name, 'group' => 'editorial_board', 'role' => 'Editor-in-Chief', 'sort_order' => $index, 'is_active' => true]);
        }
        EditorialMember::create(['name' => 'Inactive chief', 'group' => 'editorial_board', 'role' => 'Editor-in-Chief', 'is_active' => false]);
        $this->get('/')->assertOk()->assertSee('First chief')->assertSee('Second chief')->assertSee('Third chief')
            ->assertSee('Next Editors-in-Chief profile')->assertSee('Pause slideshow')->assertDontSee('Inactive chief');
        $this->get(route('editorial-board'))->assertOk()
            ->assertViewHas('chiefs', fn ($chiefs) => $chiefs->count() === 3)
            ->assertViewHas('editors', fn ($editors) => $editors->isEmpty())
            ->assertSee('First chief')->assertSee('Second chief')->assertSee('Third chief')->assertDontSee('Inactive chief');
    }

    public function test_social_logos_preserve_accessible_names_and_destinations(): void
    {
        Setting::put('social.youtube', 'https://www.youtube.com/@testjournal', 'social', true);
        Setting::put('social.linkedin', 'https://www.linkedin.com/company/testjournal', 'social', true);
        $this->get('/')->assertOk()->assertSee('class="social-link"', false)
            ->assertSee('aria-label="YouTube"', false)->assertSee('aria-label="LinkedIn"', false)
            ->assertSee('https://www.youtube.com/@testjournal')->assertSee('https://www.linkedin.com/company/testjournal');
    }

    public function test_workflow_phase_filters_include_scheduled_legacy_records_and_explain_next_actions(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = $this->person('super-admin');
        $article = Article::factory()->create(['status' => 'scheduled', 'title' => 'Scheduled publication']);
        $other = Article::factory()->draft()->create(['title' => 'Unrelated draft']);
        $this->actingAs($admin)->get(route('workflow.index', ['stages' => ['scheduled', 'published']]))->assertOk()
            ->assertSee('How the workflow works')->assertSee('Scheduled publication')->assertDontSee('Unrelated draft');
        $this->get(route('workflow.show', $article))->assertOk()->assertSee('What happens next')->assertSee('The scheduler will publish');
        $this->assertNotSame($article->id, $other->id);
    }

    public function test_super_admin_created_reviewer_is_approved_but_must_verify_email(): void
    {
        Notification::fake();
        $this->app->bind(UncompromisedVerifier::class, fn () => new class implements UncompromisedVerifier
        {
            public function verify($data)
            {
                return true;
            }
        });
        $this->seed(RolePermissionSeeder::class);
        $admin = $this->person('super-admin');
        $role = Role::where('slug', 'reviewer')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'New reviewer', 'email' => 'reviewer@example.test',
            'password' => 'Strong$Journal123', 'password_confirmation' => 'Strong$Journal123',
            'roles' => [$role->id], 'is_active' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $reviewer = User::where('email', 'reviewer@example.test')->firstOrFail();
        $this->assertTrue($reviewer->isActive());
        $this->assertTrue($reviewer->hasRole('reviewer'));
        $this->assertFalse($reviewer->hasVerifiedEmail());
        $this->assertSame($admin->id, $reviewer->approved_by_id);
        Notification::assertSentTo($reviewer, QueuedVerifyEmail::class);
        $this->flushSession();
        $this->actingAs($reviewer)->get(route('reviewer.dashboard'))->assertRedirect(route('verification.notice'));
        $this->flushSession();
        $this->actingAs($this->person('admin'))->get(route('admin.users.create'))->assertForbidden();
    }

    public function test_homepage_setup_preserves_existing_uploaded_information(): void
    {
        $discipline = Category::factory()->create([
            'name' => 'Preventive Cardiology', 'parent_id' => null,
            'description' => 'Previously entered discipline information',
            'image_path' => 'categories/existing-image.jpg',
            'seo_title' => 'Existing SEO title', 'seo_description' => 'Existing SEO description',
        ]);
        $original = $discipline->fresh()->getRawOriginal();
        $articles = collect([
            'unveiling-cardio-ankle-vascular-index-an-independent-predictor-of-hypertensive-response-to-exercise',
            'prospective-non-interventional-observational-study-assessing-the-effectiveness-of-perindopril-and-perindoprilindapamide-in-a-bangladeshi-population-of-patients-with-hypertension',
        ])->map(fn ($slug) => Article::factory()->published()->create([
            'title' => str_replace('-', ' ', $slug), 'category_id' => $discipline->id,
            'is_featured' => false, 'is_homepage_latest' => true,
            'content' => 'Previously uploaded article content',
        ]));
        $setup = app(CardiologyHomepageSetup::class);
        $report = $setup->apply(true);
        $discipline->refresh();
        foreach (['name', 'description', 'image_path', 'seo_title', 'seo_description'] as $field) {
            $this->assertSame($original[$field], $discipline->getRawOriginal($field));
        }
        foreach ($articles as $article) {
            $this->assertTrue($article->fresh()->is_featured);
            $this->assertSame('Previously uploaded article content', $article->fresh()->content);
        }
        $setup->restore($report['before']);
        $this->assertSame($original, $discipline->fresh()->getRawOriginal());
        $this->assertSame(1, Category::count());
        foreach ($articles as $article) {
            $this->assertFalse($article->fresh()->is_featured);
            $this->assertTrue($article->fresh()->is_homepage_latest);
        }
    }

    private function person(string $role): User
    {
        $user = User::factory()->create(['status' => 'active', 'is_active' => true, 'email_verified_at' => now()]);
        $user->roles()->attach(Role::where('slug', $role)->firstOrFail());

        return $user;
    }
}
