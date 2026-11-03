<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Enums\ArticleStatus;
use App\Events\ArticleStatusChanged;
use App\Models\Article;
use App\Models\ManuscriptWorkflow;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Submission;
use App\Models\User;
use App\Services\ArticleWorkflowService;
use Database\Seeders\RolePermissionSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class EditorialDraftPublicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Event::fake([ArticleStatusChanged::class]);
    }

    public function test_admin_can_publish_a_saved_sixth_editorial_article(): void
    {
        $admin = $this->roleUser('admin');
        Article::factory()->count(5)->published()->create(['created_by_id' => $admin->id]);
        $this->actingAs($admin)->post(route('admin.articles.store'), [
            'title' => 'Sixth editorial article', 'content' => '<p>Saved editorial content.</p>',
            'created_by_id' => $admin->id, 'publication_type' => 'editorial',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $article = Article::where('title', 'Sixth editorial article')->firstOrFail();
        $this->assertSame(ArticleStatus::Draft, $article->status);
        $this->get(route('admin.articles.show', $article))->assertOk()->assertSee('Publish now');
        $this->post(route('admin.articles.action', $article), ['action' => 'publish'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(ArticleStatus::Published, $article->fresh()->status);
        $this->assertNotNull($article->fresh()->published_at);
        $this->assertSame(6, Article::published()->count());
        $this->get(route('articles.show', $article))->assertOk();
        Event::assertDispatched(ArticleStatusChanged::class, fn ($event) => $event->article->id === $article->id && $event->from === ArticleStatus::Draft
            && $event->to === ArticleStatus::Published && $event->actor->id === $admin->id);
    }

    public function test_bulk_publish_handles_more_than_five_editorial_drafts_and_skips_author_drafts(): void
    {
        $admin = $this->roleUser('admin');
        $drafts = Article::factory()->count(7)->draft()->create(['created_by_id' => $admin->id]);
        $authorDraft = Article::factory()->draft()->create(['created_by_id' => $this->roleUser('author')->id]);
        $this->actingAs($admin)->post(route('admin.articles.bulk'), [
            'action' => 'publish', 'article_ids' => [...$drafts->modelKeys(), $authorDraft->id],
        ])->assertRedirect()->assertSessionHas('success', 'Bulk action completed for 7 article(s); 1 skipped due to workflow state.');
        $this->assertSame(7, Article::published()->count());
        $this->assertSame(ArticleStatus::Draft, $authorDraft->fresh()->status);
    }

    public function test_author_owned_draft_cannot_bypass_review_through_admin_publication(): void
    {
        $admin = $this->roleUser('admin');
        $article = Article::factory()->draft()->create(['created_by_id' => $this->roleUser('author')->id]);
        $this->actingAs($admin)->get(route('admin.articles.show', $article))->assertOk()->assertDontSee('Publish now');
        $this->post(route('admin.articles.action', $article), ['action' => 'publish'])
            ->assertRedirect()->assertSessionHasErrors('action');
        $this->assertSame(ArticleStatus::Draft, $article->fresh()->status);
    }

    public function test_editor_cannot_use_administrator_draft_publication(): void
    {
        $editor = $this->roleUser('editor');
        $article = Article::factory()->draft()->create(['created_by_id' => $editor->id, 'assigned_editor_id' => $editor->id]);
        $this->actingAs($editor)->post(route('admin.articles.action', $article), ['action' => 'publish'])
            ->assertRedirect()->assertSessionHasErrors('action');
        $this->assertSame(ArticleStatus::Draft, $article->fresh()->status);
    }

    public function test_existing_submission_or_managed_workflow_cannot_use_editorial_publication(): void
    {
        $admin = $this->roleUser('admin');
        $submitted = Article::factory()->draft()->create(['created_by_id' => $admin->id]);
        Submission::factory()->create(['article_id' => $submitted->id, 'submitted_by_id' => $admin->id]);
        $managed = Article::factory()->draft()->create(['created_by_id' => $admin->id]);
        ManuscriptWorkflow::create(['article_id' => $managed->id, 'stage' => 'draft', 'data' => []]);
        $this->actingAs($admin)->post(route('admin.articles.action', $submitted), ['action' => 'publish'])
            ->assertRedirect()->assertSessionHasErrors('action');
        $this->post(route('admin.articles.action', $managed), ['action' => 'publish'])->assertStatus(409);
        $this->assertSame(0, Article::published()->count());
    }

    public function test_generic_transition_still_cannot_publish_a_draft(): void
    {
        $admin = $this->roleUser('admin');
        $article = Article::factory()->draft()->create(['created_by_id' => $admin->id]);
        $this->expectException(DomainException::class);
        app(ArticleWorkflowService::class)->transition($article, ArticleStatus::Published, $admin);
    }

    public function test_administrator_without_publication_permission_cannot_publish(): void
    {
        $admin = $this->roleUser('admin');
        $role = Role::where('slug', 'admin')->firstOrFail();
        $role->permissions()->detach(Permission::where('slug', 'articles.publish')->firstOrFail()->id);
        $article = Article::factory()->draft()->create(['created_by_id' => $admin->id]);
        $this->actingAs($admin)->post(route('admin.articles.action', $article), ['action' => 'publish'])->assertForbidden();
        $this->assertFalse(app(ArticleWorkflowService::class)->canPublishEditorialDraft($article, $admin));
        $this->assertSame(ArticleStatus::Draft, $article->fresh()->status);
    }

    public function test_incomplete_editorial_content_cannot_be_published(): void
    {
        $admin = $this->roleUser('admin');
        $article = Article::factory()->draft()->create(['created_by_id' => $admin->id, 'content' => '<p> </p>']);
        $this->actingAs($admin)->post(route('admin.articles.action', $article), ['action' => 'publish'])
            ->assertRedirect()->assertSessionHasErrors('action');
        $this->assertSame(ArticleStatus::Draft, $article->fresh()->status);
        Event::assertNotDispatched(ArticleStatusChanged::class);
    }

    private function roleUser(string $slug): User
    {
        $user = User::factory()->create(['status' => 'active', 'is_active' => true, 'email_verified_at' => now()]);
        $user->roles()->attach(Role::where('slug', $slug)->firstOrFail());

        return $user;
    }
}
