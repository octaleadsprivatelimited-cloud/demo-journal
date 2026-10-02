<?php

namespace Tests\Feature\Portal;

use App\Models\{Article, Role, User};
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReviewerAssignmentPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_simple_assignment_flow_and_duplicate_exclusion(): void
    {
        Notification::fake();
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create(['status'=>'active','is_active'=>true,'email_verified_at'=>now()]);
        $admin->roles()->attach(Role::where('slug','admin')->firstOrFail());
        $reviewer = User::factory()->create(['status'=>'active','is_active'=>true,'email_verified_at'=>now()]);
        $reviewer->roles()->attach(Role::where('slug','reviewer')->firstOrFail());
        $article = Article::factory()->create(['status'=>'submitted']);
        $this->actingAs($admin)->get('/admin/assign-reviewer')->assertOk()->assertSee($article->title);
        $this->get('/admin/assign-reviewer/'.$article->slug)->assertOk()->assertSee($reviewer->name)->assertSee('Send review invitation');
        $this->post('/admin/assign-reviewer/'.$article->slug, ['reviewer_id'=>$reviewer->id,'deadline'=>now()->addDays(21)->toDateString(),'invitation_deadline'=>now()->addDays(7)->toDateString(),'editor_message'=>'Please review this manuscript.'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('reviews',['article_id'=>$article->id,'reviewer_id'=>$reviewer->id,'status'=>'assigned']);
        $this->get('/admin/assign-reviewer/'.$article->slug)->assertOk()->assertSee('No eligible reviewers are available');
        $draft = Article::factory()->draft()->create();
        $this->get('/admin/assign-reviewer/'.$draft->slug)->assertOk()->assertSee('Complete editorial checks first')->assertDontSee('Send review invitation');
        $this->actingAs($reviewer)->get('/admin/assign-reviewer')->assertForbidden();
    }
}
