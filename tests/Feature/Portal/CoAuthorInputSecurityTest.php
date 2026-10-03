<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Models\Article;
use App\Models\Author;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CoAuthorInputSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_details_reject_privileged_and_unknown_fields_before_saving(): void
    {
        $author = $this->authorUser();
        $other = User::factory()->create();
        foreach (['user_id' => $other->id, 'avatar_path' => 'private/record.svg', 'website_url' => 'javascript:alert(1)', 'is_verified' => true, 'unknown_column' => 'invalid'] as $key => $value) {
            $this->actingAs($author)->postJson(route('author.articles.store'), $this->draft([
                'name' => 'Independent co-author', 'email' => 'coauthor@example.test', $key => $value,
            ]))->assertUnprocessable()->assertJsonValidationErrors('author_details.0');
        }
        $this->assertDatabaseCount('articles', 0);
        $this->assertDatabaseCount('authors', 1);
        $this->assertNull($other->author()->first());
    }

    public function test_valid_manuscript_authors_remain_unlinked_to_registered_accounts(): void
    {
        $user = $this->authorUser();
        $this->actingAs($user)->post(route('author.articles.store'), $this->draft([
            'name' => $user->name, 'email' => $user->email, 'organization' => 'Research Institute',
        ]))->assertSessionHasNoErrors()->assertRedirect();
        $article = Article::query()->firstOrFail();
        $coauthor = $article->authors()->firstOrFail();
        $this->assertNull($coauthor->user_id);
        $this->assertFalse($coauthor->is_verified);
        $this->assertTrue($coauthor->is_active);
        $this->assertSame($user->id, $user->author()->firstOrFail()->user_id);
    }

    public function test_matching_registered_author_details_can_reuse_an_existing_orcid_without_profile_changes(): void
    {
        $user = $this->authorUser();
        $profile = $user->author()->firstOrFail();
        $profile->update(['orcid' => '0000-0001-2345-6789']);
        $detail = $profile->only(['name', 'email', 'organization', 'department', 'country', 'orcid']);
        $this->actingAs($user)->post(route('author.articles.store'), $this->draft($detail))
            ->assertSessionHasNoErrors()->assertRedirect();
        $article = Article::query()->firstOrFail();
        $this->assertSame($profile->id, $article->authors()->firstOrFail()->id);
        $this->assertSame($detail, $profile->fresh()->only(array_keys($detail)));
        $this->assertDatabaseCount('authors', 1);
    }

    public function test_existing_orcid_with_changed_details_returns_field_validation_without_overwriting_the_profile(): void
    {
        $user = $this->authorUser();
        $profile = $user->author()->firstOrFail();
        $profile->update(['orcid' => '0000-0001-2345-6789']);
        $detail = $profile->only(['name', 'email', 'organization', 'department', 'country', 'orcid']);
        $detail['organization'] = 'A different institution';
        $this->actingAs($user)->postJson(route('author.articles.store'), $this->draft($detail))
            ->assertUnprocessable()->assertJsonValidationErrors('author_details.0.orcid');
        $this->assertNotSame($detail['organization'], $profile->fresh()->organization);
        $this->assertDatabaseCount('articles', 0);
        $this->assertDatabaseCount('authors', 1);
    }

    public function test_archived_orcid_returns_field_validation_instead_of_a_database_error(): void
    {
        $user = $this->authorUser();
        $archived = Author::factory()->create(['orcid' => '0000-0001-2345-6789']);
        $detail = $archived->only(['name', 'email', 'organization', 'department', 'country', 'orcid']);
        $archived->delete();
        $this->actingAs($user)->postJson(route('author.articles.store'), $this->draft($detail))
            ->assertUnprocessable()->assertJsonValidationErrors('author_details.0.orcid');
        $this->assertTrue($archived->fresh()->trashed());
        $this->assertDatabaseCount('articles', 0);
    }

    private function draft(array $detail): array
    {
        return ['title' => 'A secure draft', 'publication_type' => 'research', 'intent' => 'draft', 'corresponding_index' => 0, 'author_details' => [$detail]];
    }

    private function authorUser(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', 'author')->firstOrFail());
        Author::factory()->create(['user_id' => $user->id, 'email' => $user->email]);

        return $user;
    }
}
