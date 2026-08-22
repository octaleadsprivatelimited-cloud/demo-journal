<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Author;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuthorizationBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_every_protected_workspace(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->get('/author/dashboard')->assertRedirect('/login');
        $this->get('/reviewer/dashboard')->assertRedirect('/login');
    }

    public function test_author_role_cannot_enter_admin_workspace(): void
    {
        $role = Role::query()->create(['name' => 'Author', 'slug' => 'author']);
        $user = User::factory()->create();
        $user->roles()->attach($role);
        Author::factory()->create(['user_id' => $user->getKey(), 'email' => $user->email]);

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_article_policy_blocks_cross_author_mutation(): void
    {
        $role = Role::query()->create(['name' => 'Author', 'slug' => 'author']);
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $owner->roles()->attach($role);
        $other->roles()->attach($role);
        Author::factory()->create(['user_id' => $owner->getKey(), 'email' => $owner->email]);
        Author::factory()->create(['user_id' => $other->getKey(), 'email' => $other->email]);
        $article = Article::factory()->draft()->create(['created_by_id' => $owner->getKey()]);

        $this->assertFalse($other->can('update', $article));
        $this->assertFalse($other->can('submit', $article));
        $this->assertTrue($owner->can('update', $article));
    }
}
