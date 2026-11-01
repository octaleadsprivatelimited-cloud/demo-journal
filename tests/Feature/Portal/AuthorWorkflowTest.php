<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Models\Article;
use App\Models\Author;
use App\Models\Category;
use App\Models\ManuscriptWorkflow;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class AuthorWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_creates_a_draft_and_owner_is_forced_as_corresponding_author(): void
    {
        [$user, $author] = $this->authorUser();
        $category = Category::factory()->create();
        $response = $this->actingAs($user)->post(route('author.articles.store'), [
            'title' => 'A Durable Research Record', 'abstract' => 'A complete abstract.', 'content' => '<p>Long-form research content.</p>',
            'category_id' => $category->id, 'publication_type' => 'research',
        ]);
        $article = Article::query()->where('created_by_id', $user->id)->firstOrFail();
        $response->assertRedirect(route('author.articles.edit', $article));
        $this->assertDatabaseHas('article_authors', ['article_id' => $article->id, 'author_id' => $author->id, 'is_corresponding' => true, 'sort_order' => 0]);
    }

    public function test_autosave_rejects_a_stale_optimistic_timestamp(): void
    {
        [$user] = $this->authorUser();
        $article = Article::factory()->create(['created_by_id' => $user->id, 'status' => 'draft']);
        $this->actingAs($user)->patchJson(route('author.articles.autosave', $article), [
            'title' => 'Stale overwrite',
            'updated_at' => now()->subHour()->toISOString(),
            'draft_version' => $article->fresh()->draftVersion(),
        ])
            ->assertConflict()->assertJsonPath('code', 'version_conflict');
    }

    public function test_autosave_tokens_detect_stale_edits_within_the_same_second(): void
    {
        $this->travelTo(now()->startOfSecond());
        [$user] = $this->authorUser();
        $article = Article::factory()->draft()->create(['created_by_id' => $user->id])->fresh();
        $url = route('author.articles.autosave', $article);
        $initialTimestamp = $article->updated_at->toISOString();
        $initialVersion = $article->draftVersion();

        $firstSave = $this->actingAs($user)->patchJson($url, [
            'abstract' => 'First saved abstract.',
            'updated_at' => $initialTimestamp,
            'draft_version' => $initialVersion,
        ])->assertOk();
        $this->assertSame($initialTimestamp, $firstSave->json('updated_at'));
        $this->assertNotSame($initialVersion, $firstSave->json('draft_version'));

        $secondSave = $this->patchJson($url, [
            'abstract' => 'Second saved abstract.',
            'updated_at' => $firstSave->json('updated_at'),
            'draft_version' => $firstSave->json('draft_version'),
        ])->assertOk();
        $this->assertSame($initialTimestamp, $secondSave->json('updated_at'));
        $this->assertNotSame($firstSave->json('draft_version'), $secondSave->json('draft_version'));
        $this->assertSame($article->fresh()->draftVersion(), $secondSave->json('draft_version'));

        $this->patchJson($url, [
            'abstract' => 'Stale overwrite.',
            'updated_at' => $initialTimestamp,
            'draft_version' => $initialVersion,
        ])->assertConflict()->assertJsonPath('code', 'version_conflict');

        $this->assertSame('Second saved abstract.', $article->fresh()->abstract);
    }

    public function test_autosave_requires_a_version_token_after_authorization(): void
    {
        [$owner] = $this->authorUser();
        [$other] = $this->authorUser();
        $article = Article::factory()->draft()->create(['created_by_id' => $owner->id]);
        $originalAbstract = $article->abstract;
        $url = route('author.articles.autosave', $article);

        $this->actingAs($owner)->patchJson($url, ['abstract' => 'Unversioned overwrite.'])
            ->assertConflict()->assertJsonPath('code', 'version_conflict');
        $this->actingAs($other)->patchJson($url, ['abstract' => 'Unauthorized overwrite.'])->assertForbidden();

        $this->assertSame($originalAbstract, $article->fresh()->abstract);
    }

    public function test_restored_input_retains_its_stale_version_instead_of_receiving_the_current_version(): void
    {
        $this->travelTo(now()->startOfSecond());
        [$user] = $this->authorUser();
        $article = Article::factory()->draft()->create([
            'created_by_id' => $user->id,
            'abstract' => 'Original tab abstract.',
        ])->fresh();
        $initialVersion = $article->draftVersion();
        $initialTimestamp = $article->updated_at->toISOString();
        $url = route('author.articles.autosave', $article);

        $saved = $this->actingAs($user)->patchJson($url, [
            'abstract' => 'Newer saved abstract.',
            'updated_at' => $initialTimestamp,
            'draft_version' => $initialVersion,
        ])->assertOk();
        $this->assertSame($initialTimestamp, $saved->json('updated_at'));

        $this->withSession(['_old_input' => [
            'abstract' => 'Original tab abstract.',
            'updated_at' => $initialTimestamp,
            'draft_version' => $initialVersion,
        ]])->get(route('author.articles.edit', $article))
            ->assertOk()
            ->assertSee('Original tab abstract.')
            ->assertSee('name="draft_version" value="'.$initialVersion.'"', false)
            ->assertSee('name="updated_at" value="'.$initialTimestamp.'"', false)
            ->assertDontSee('name="draft_version" value="'.$saved->json('draft_version').'"', false);

        $this->patchJson($url, [
            'abstract' => 'Original tab abstract.',
            'updated_at' => $initialTimestamp,
            'draft_version' => $initialVersion,
        ])->assertConflict()->assertJsonPath('code', 'version_conflict');

        $this->assertSame('Newer saved abstract.', $article->fresh()->abstract);
    }

    public function test_restored_input_with_a_missing_or_malformed_version_does_not_receive_a_fresh_version(): void
    {
        [$user] = $this->authorUser();
        $article = Article::factory()->draft()->create(['created_by_id' => $user->id])->fresh();
        $originalAbstract = $article->abstract;
        $version = $article->draftVersion();
        $timestamp = $article->updated_at->toISOString();
        $this->actingAs($user);

        foreach ([
            ['draft_version' => ['unexpected'], 'updated_at' => ['unexpected']],
            [],
            ['draft_version' => 'invalid-token', 'updated_at' => $timestamp],
            ['draft_version' => str_repeat('A', 64), 'updated_at' => $timestamp],
        ] as $oldInput) {
            $expectedTimestamp = is_string($oldInput['updated_at'] ?? null) ? $oldInput['updated_at'] : '';
            $this->withSession(['_old_input' => $oldInput + ['abstract' => 'Restored unsaved text.']])
                ->get(route('author.articles.edit', $article))
                ->assertOk()
                ->assertSee('Restored unsaved text.')
                ->assertSee('name="draft_version" value=""', false)
                ->assertSee('name="updated_at" value="'.$expectedTimestamp.'"', false)
                ->assertDontSee('name="draft_version" value="'.$version.'"', false);

            $this->patchJson(route('author.articles.autosave', $article), [
                'abstract' => 'Restored unsaved text.',
                'updated_at' => $expectedTimestamp,
                'draft_version' => '',
            ])->assertConflict()->assertJsonPath('code', 'version_conflict');
        }

        $this->assertSame($originalAbstract, $article->fresh()->abstract);
    }

    public function test_autosave_rechecks_submission_state_after_route_binding(): void
    {
        [$user] = $this->authorUser();
        $article = Article::factory()->draft()->create(['created_by_id' => $user->id])->fresh();
        $originalAbstract = $article->abstract;
        $version = $article->draftVersion();
        Route::bind('article', function (string $slug): Article {
            $boundArticle = Article::query()->where('slug', $slug)->firstOrFail();
            Article::query()->whereKey($boundArticle->id)->update(['status' => 'submitted']);

            return $boundArticle;
        });

        $this->actingAs($user)->patchJson(route('author.articles.autosave', $article), [
            'abstract' => 'Late overwrite after submission.',
            'draft_version' => $version,
        ])->assertForbidden();

        $this->assertSame('submitted', $article->fresh()->status->value);
        $this->assertSame($originalAbstract, $article->fresh()->abstract);
    }

    public function test_autosave_rechecks_workflow_state_after_route_binding(): void
    {
        [$user] = $this->authorUser();
        $article = Article::factory()->draft()->create(['created_by_id' => $user->id])->fresh();
        $originalAbstract = $article->abstract;
        $version = $article->draftVersion();
        Route::bind('article', function (string $slug): Article {
            $boundArticle = Article::query()->where('slug', $slug)->firstOrFail();
            $boundArticle->setRelation('workflow', null);
            ManuscriptWorkflow::create(['article_id' => $boundArticle->id, 'stage' => 'under_review', 'data' => []]);

            return $boundArticle;
        });

        $this->actingAs($user)->patchJson(route('author.articles.autosave', $article), [
            'abstract' => 'Late overwrite after editorial locking.',
            'draft_version' => $version,
        ])->assertForbidden();

        $this->assertSame($originalAbstract, $article->fresh()->abstract);
        $this->assertSame('under_review', $article->fresh()->workflow->stage);
    }

    public function test_title_autosaves_preserve_the_original_form_endpoints(): void
    {
        [$user] = $this->authorUser();
        $article = Article::factory()->draft()->create(['created_by_id' => $user->id, 'title' => 'Original manuscript title']);
        $slug = $article->slug;
        $autosaveUrl = route('author.articles.autosave', $article);
        $editUrl = route('author.articles.edit', $article);

        $firstSave = $this->actingAs($user)->patchJson($autosaveUrl, [
            'title' => 'Corrected manuscript title',
            'updated_at' => $article->updated_at->toISOString(),
            'draft_version' => $article->fresh()->draftVersion(),
        ])->assertOk();

        $this->patchJson($autosaveUrl, [
            'title' => 'Final manuscript title',
            'updated_at' => $firstSave->json('updated_at'),
            'draft_version' => $firstSave->json('draft_version'),
        ])->assertOk();

        $this->assertSame($slug, $article->fresh()->slug);
        $this->get($editUrl)->assertOk()->assertSee('Final manuscript title');
    }

    public function test_manual_title_edits_redirect_to_a_resolvable_manuscript_url(): void
    {
        [$user] = $this->authorUser();
        $article = Article::factory()->draft()->create(['created_by_id' => $user->id, 'title' => 'Original manuscript title']);
        $slug = $article->slug;
        $workflowUrl = route('workflow.show', $article);

        $this->actingAs($user)->put(route('author.articles.update', $article), [
            'title' => 'Corrected manuscript title',
            'publication_type' => 'research',
            'intent' => 'draft',
        ])->assertSessionHasNoErrors()->assertRedirect($workflowUrl);

        $this->assertSame($slug, $article->fresh()->slug);
        $this->get($workflowUrl)->assertOk()->assertSee('Corrected manuscript title');
    }

    public function test_edit_form_exposes_one_populated_autosave_endpoint(): void
    {
        [$user] = $this->authorUser();
        $article = Article::factory()->draft()->create(['created_by_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('author.articles.edit', $article))->assertOk();
        preg_match_all('/\sdata-autosave(?:\s|=)/', $response->getContent(), $matches);

        $this->assertCount(1, $matches[0]);
        $response->assertSee('data-autosave-status', false)->assertSee('Use Save draft for authors and files; confirm declarations when submitting.');
        $response->assertSee('data-autosave="'.route('author.articles.autosave', $article).'"', false);
        $response->assertSee('name="draft_version" value="'.$article->fresh()->draftVersion().'"', false);
    }

    public function test_draft_record_opens_the_complete_wizard_without_an_incomplete_submit_form(): void
    {
        [$user] = $this->authorUser();
        $article = Article::factory()->draft()->create(['created_by_id' => $user->id]);

        $this->actingAs($user)->get(route('author.articles.show', $article))->assertOk()
            ->assertSee('Complete files and declarations to submit')
            ->assertSee('href="'.route('author.articles.edit', $article).'"', false)
            ->assertDontSee('action="'.route('author.articles.submit', $article).'"', false);

        $wizard = $this->get(route('author.articles.edit', $article))->assertOk();
        foreach (['original', 'exclusive', 'authors_approve', 'ethics', 'conflicts'] as $declaration) {
            $wizard->assertSee('name="declarations['.$declaration.']"', false);
        }
        $wizard->assertSee('name="manuscript"', false)->assertSee('name="cover_letter"', false);
    }

    public function test_author_cannot_delete_their_own_articles_at_any_stage(): void
    {
        [$user] = $this->authorUser();
        foreach (['draft', 'rejected', 'approved', 'published'] as $status) {
            $article = Article::factory()->create(['created_by_id' => $user->id, 'status' => $status]);
            $this->assertFalse($user->can('delete', $article));
        }

        $this->assertFalse(Route::has('author.articles.destroy'));
    }

    public function test_author_cannot_edit_another_authors_manuscript(): void
    {
        [$user] = $this->authorUser();
        [$other] = $this->authorUser();
        $article = Article::factory()->create(['created_by_id' => $other->id, 'status' => 'draft']);
        $this->actingAs($user)->get(route('author.articles.edit', $article))->assertForbidden();
    }

    /** @return array{User, Author} */
    private function authorUser(): array
    {
        $role = Role::query()->firstOrCreate(['slug' => 'author'], ['name' => 'Author', 'is_system' => true]);
        $permissions = collect(['articles.create', 'articles.update', 'articles.submit'])->map(fn (string $slug) => Permission::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => str($slug)->replace('.', ' ')->headline(), 'group' => 'articles'],
        ));
        $role->permissions()->syncWithoutDetaching($permissions->pluck('id'));
        $user = User::factory()->create();
        $user->roles()->attach($role);
        $author = Author::factory()->create(['user_id' => $user->id, 'email' => $user->email]);

        return [$user, $author];
    }
}
