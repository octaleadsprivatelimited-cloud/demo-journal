<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Models\Article;
use App\Models\Author;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->actingAs($user)->patchJson(route('author.articles.autosave', $article), ['title' => 'Stale overwrite', 'updated_at' => now()->subHour()->toISOString()])
            ->assertConflict()->assertJsonPath('code', 'version_conflict');
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
    }

    public function test_author_cannot_delete_their_own_articles_at_any_stage(): void
    {
        [$user] = $this->authorUser();
        foreach (['draft', 'rejected', 'approved', 'published'] as $status) {
            $article = Article::factory()->create(['created_by_id' => $user->id, 'status' => $status]);
            $this->assertFalse($user->can('delete', $article));
        }

        $this->assertFalse(\Illuminate\Support\Facades\Route::has('author.articles.destroy'));
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
