<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Author;
use App\Models\Category;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class ApiSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_api_returns_only_published_articles(): void
    {
        $published = Article::factory()->published()->create();
        $draft = Article::factory()->draft()->create();

        $this->getJson('/api/v1/articles')
            ->assertOk()
            ->assertJsonFragment(['slug' => $published->slug])
            ->assertJsonMissing(['slug' => $draft->slug]);

        $this->getJson('/api/v1/articles/'.$draft->slug)->assertNotFound();
    }

    public function test_only_active_verified_users_receive_tokens(): void
    {
        $verified = User::factory()->create(['password' => 'Correct-Horse-42!']);

        $this->postJson('/api/v1/tokens', [
            'email' => $verified->email,
            'password' => 'Correct-Horse-42!',
            'device_name' => 'integration-test',
            'abilities' => ['articles:read'],
        ])->assertCreated()->assertJsonStructure(['token', 'token_type', 'abilities']);

        $unverified = User::factory()->unverified()->create(['password' => 'Correct-Horse-42!']);

        $this->postJson('/api/v1/tokens', [
            'email' => $unverified->email,
            'password' => 'Correct-Horse-42!',
            'device_name' => 'integration-test',
        ])->assertUnprocessable();
    }

    public function test_author_api_cannot_update_another_authors_draft(): void
    {
        $authorRole = Role::query()->create(['name' => 'Author', 'slug' => 'author']);
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $owner->roles()->attach($authorRole);
        $intruder->roles()->attach($authorRole);
        Author::factory()->create(['user_id' => $owner->getKey(), 'email' => $owner->email]);
        Author::factory()->create(['user_id' => $intruder->getKey(), 'email' => $intruder->email]);
        $article = Article::factory()->draft()->create(['created_by_id' => $owner->getKey()]);
        $category = Category::factory()->create();

        $payload = [
            'title' => 'Attempted replacement title',
            'abstract' => 'A complete abstract for the attempted update.',
            'content' => '<p>Content that the second author is not permitted to overwrite.</p>',
            'category_id' => $category->getKey(),
            'publication_type' => 'research',
        ];

        Sanctum::actingAs($intruder, ['articles:write']);

        $this->patchJson('/api/v1/author/articles/'.$article->slug, $payload)
            ->assertForbidden();

        $this->assertNotSame('Attempted replacement title', $article->fresh()->title);
    }

    public function test_read_only_tokens_cannot_mutate_author_articles(): void
    {
        $authorRole = Role::query()->create(['name' => 'Author', 'slug' => 'author']);
        $user = User::factory()->create();
        $user->roles()->attach($authorRole);
        Author::factory()->create(['user_id' => $user->getKey(), 'email' => $user->email]);
        $category = Category::factory()->create();

        Sanctum::actingAs($user, ['articles:read']);

        $this->postJson('/api/v1/author/articles', [
            'title' => 'A mutation attempted with a read-only token',
            'abstract' => 'This abstract is valid but the access token cannot write manuscripts.',
            'content' => '<p>The request must be rejected before a draft is persisted.</p>',
            'category_id' => $category->getKey(),
            'publication_type' => 'research',
        ])->assertForbidden();

        $this->assertDatabaseMissing('articles', [
            'title' => 'A mutation attempted with a read-only token',
        ]);
    }
}
