<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageHeroTest extends TestCase
{
    use RefreshDatabase;

    public function test_hero_contains_only_selected_published_articles_in_editorial_order(): void
    {
        $featured = Article::factory()->published()->create(['is_featured' => true, 'published_at' => now()->subDays(4)]);
        $latest = Article::factory()->published()->create(['is_featured' => false, 'is_homepage_latest' => true]);
        Article::factory()->published()->create(['is_featured' => false, 'is_homepage_latest' => false]);
        Article::factory()->draft()->create(['is_featured' => true]);
        Article::factory()->published()->create(['is_featured' => true, 'published_at' => now()->addDay()]);
        $deleted = Article::factory()->published()->create(['is_featured' => true]);
        $deleted->delete();

        $this->get('/')->assertOk()
            ->assertViewHas('heroArticles', fn ($articles) => $articles->modelKeys() === [$featured->id, $latest->id])
            ->assertSee('data-hero-slider', false)->assertSee('data-hero-next', false);
    }

    public function test_admin_can_change_hero_placement_after_workflow_metadata_is_locked(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = $this->person('admin');
        $article = Article::factory()->published()->create(['is_featured' => false]);
        $article->workflow()->create(['stage' => 'published']);
        $this->actingAs($admin)->get(route('workflow.show', $article))->assertOk()->assertSee('Save hero placement');
        foreach (['featured', 'latest', 'hidden'] as $placement) {
            $this->post(route('admin.articles.action', $article), ['action' => 'homepage', 'placement' => $placement])
                ->assertRedirect()->assertSessionHasNoErrors();
            $this->assertDatabaseHas('articles', [
                'id' => $article->id, 'is_featured' => $placement === 'featured',
                'is_homepage_latest' => $placement === 'latest', 'status' => 'published',
            ]);
            $this->get('/')->assertViewHas('heroArticles', fn ($articles) => $articles->contains('id', $article->id) === ($placement !== 'hidden'));
        }
        $this->assertSame('published', $article->workflow->fresh()->stage);
        $this->post(route('admin.articles.action', $article), ['action' => 'homepage', 'placement' => 'invalid'])->assertSessionHasErrors('placement');
    }

    public function test_other_roles_cannot_select_homepage_articles(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $article = Article::factory()->published()->create(['is_featured' => false]);
        foreach (['editor', 'author', 'reviewer'] as $role) {
            $person = $this->person($role);
            $article->update(['assigned_editor_id' => $person->id, 'created_by_id' => $person->id]);
            $this->actingAs($person)->post(route('admin.articles.action', $article), ['action' => 'homepage', 'placement' => 'featured'])->assertForbidden();
        }
        $this->assertFalse($article->fresh()->is_featured);
    }

    public function test_empty_and_single_selection_do_not_show_slider_controls(): void
    {
        $this->get('/')->assertOk()->assertDontSee('data-hero-slider', false);
        Article::factory()->published()->create(['is_featured' => true]);
        $this->get('/')->assertOk()->assertSee('data-hero-slider', false)->assertDontSee('data-hero-next', false);
    }

    private function person(string $role): User
    {
        $user = User::factory()->create(['status' => 'active', 'is_active' => true, 'email_verified_at' => now()]);
        $user->roles()->attach(Role::where('slug', $role)->firstOrFail());
        return $user;
    }
}
