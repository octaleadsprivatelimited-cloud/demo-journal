<?php
namespace Tests\Feature\Portal;

use App\Models\{Article, Category, Role, User};
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminModuleAuditTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['status'=>'active', 'is_active'=>true, 'email_verified_at'=>now()]);
        $user->roles()->attach(Role::where('slug','super-admin')->firstOrFail());
        $this->actingAs($user);
    }

    public function test_admin_modules_and_public_pages_render(): void
    {
        $this->signIn();
        foreach (['admin.dashboard','admin.articles.index','admin.articles.create','admin.categories.index','admin.tags.index','admin.submissions.index','admin.reviews.index','admin.assign-reviewer.index','admin.media.index','admin.uploads.index','admin.users.index','admin.roles.index','admin.comments.index','admin.contacts.index','admin.newsletter.index','admin.settings.index','admin.audit.index'] as $route) {
            $this->get(route($route))->assertOk();
        }
        foreach (['/','/articles','/archive','/categories','/authors','/search'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_article_filters_combine_and_trash_is_separate(): void
    {
        $this->signIn();
        $category = Category::factory()->create();
        $match = Article::factory()->create(['title'=>'Smarter Irrigation Research','category_id'=>$category->id,'status'=>'draft']);
        Article::factory()->create(['title'=>'Solar Research','category_id'=>$category->id,'status'=>'published']);
        $trash = Article::factory()->create(['title'=>'Removed Irrigation','status'=>'draft']);
        $trash->delete();
        $this->get(route('admin.articles.index',['q'=>'IRRIG','status'=>'draft','category'=>$category->id]))
            ->assertOk()->assertViewHas('articles', fn($rows)=>$rows->count()===1 && $rows->first()->id===$match->id);
        $this->get(route('admin.articles.index'))->assertOk()->assertViewHas('articles',fn($rows)=>$rows->count()===2);
        $this->get(route('admin.articles.index',['trashed'=>'only']))->assertOk()->assertViewHas('articles',fn($rows)=>$rows->count()===1 && $rows->first()->id===$trash->id);
        $this->get(route('admin.articles.index',['q'=>'no-such-title']))->assertOk()->assertViewHas('articles',fn($rows)=>$rows->isEmpty());
    }
}
