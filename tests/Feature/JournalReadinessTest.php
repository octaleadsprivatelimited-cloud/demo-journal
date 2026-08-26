<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ReviewStatus;
use App\Models\Article;
use App\Models\EditorialMember;
use App\Models\IndexingService;
use App\Models\JournalIssue;
use App\Models\JournalVolume;
use App\Models\Review;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class JournalReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_super_admin_can_create_volume_and_rich_issue_record(): void
    {
        $admin = $this->activeUser('super-admin');
        $volume = JournalVolume::query()->create(['number' => '12', 'year' => 2026, 'title' => 'Research advances']);

        $this->actingAs($admin)->post(route('admin.readiness.store', 'issues'), [
            'journal_volume_id' => $volume->id,
            'number' => '2',
            'title' => 'Special issue',
            'description' => 'A curated issue about current research practice.',
            'publication_date' => '2026-08-01',
            'is_current' => '1',
            'is_active' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('journal_issues', [
            'journal_volume_id' => $volume->id,
            'number' => '2',
            'description' => 'A curated issue about current research practice.',
        ]);
    }

    public function test_archive_exposes_issue_description_and_only_published_articles(): void
    {
        $volume = JournalVolume::query()->create(['number' => '12', 'year' => 2026]);
        $issue = JournalIssue::query()->create(['journal_volume_id' => $volume->id, 'number' => '2', 'title' => 'Special issue', 'description' => 'Curated peer-reviewed scholarship.']);
        $published = Article::factory()->published()->create(['journal_issue_id' => $issue->id, 'title' => 'Published archive article']);
        Article::factory()->draft()->create(['journal_issue_id' => $issue->id, 'title' => 'Unpublished archive article']);

        $this->get(route('archive.index'))->assertOk()->assertSeeText('Curated peer-reviewed scholarship.');
        $this->get(route('archive.issue', $issue))->assertOk()->assertSeeText($published->title)->assertDontSeeText('Unpublished archive article');
    }

    public function test_editorial_and_verified_indexing_records_are_publicly_visible(): void
    {
        EditorialMember::query()->create(['name' => 'Dr Ada Editor', 'group' => 'editorial_board', 'role' => 'Editor-in-Chief', 'institution' => 'SJC University', 'is_active' => true]);
        IndexingService::query()->create(['name' => 'Verified Index', 'status' => 'verified', 'official_url' => 'https://example.test/index', 'is_active' => true]);
        IndexingService::query()->create(['name' => 'Unverified Index', 'status' => 'pending_verification', 'is_active' => true]);

        $this->get(route('editorial-board'))->assertOk()->assertSeeText('Dr Ada Editor');
        $this->get(route('policies.show', 'indexing'))->assertOk()->assertSeeText('Verified Index')->assertDontSeeText('Unverified Index');
    }

    public function test_contributor_can_sign_in_to_the_author_workspace(): void
    {
        $user = $this->activeUser('contributor', 'contributor@example.test');

        $this->post(route('contributor.login.store'), ['email' => $user->email, 'password' => 'Strong!Portal123'])
            ->assertRedirect(route('author.dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->get(route('author.articles.create'))->assertOk();
    }

    public function test_reviewer_can_decline_an_assignment_and_declare_conflict(): void
    {
        $reviewer = $this->activeUser('reviewer');
        $review = Review::factory()->create(['reviewer_id' => $reviewer->id, 'status' => ReviewStatus::Assigned]);

        $this->actingAs($reviewer)->post(route('reviewer.reviews.decline', $review), [
            'response_note' => 'This manuscript overlaps with my current collaboration.',
            'conflict_declared' => '1',
        ])->assertRedirect(route('reviewer.dashboard'));

        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'status' => ReviewStatus::Declined->value, 'conflict_declared' => true]);
        $this->assertNotNull($review->fresh()->responded_at);
    }

    private function activeUser(string $role, ?string $email = null): User
    {
        $user = User::factory()->create([
            'email' => $email ?? fake()->unique()->safeEmail(),
            'password' => 'Strong!Portal123',
            'email_verified_at' => now(),
            'status' => 'active',
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::query()->where('slug', $role)->sole());

        return $user;
    }
}
