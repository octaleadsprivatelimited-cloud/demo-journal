<?php

namespace Tests\Feature\Portal;

use App\Models\{Article, ManuscriptWorkflow, Role, Submission, User};
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmissionStatusNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_managed_submission_links_to_workflow_and_stale_form_does_not_change_status(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create(['status'=>'active','is_active'=>true,'email_verified_at'=>now()]);
        $admin->roles()->attach(Role::where('slug','admin')->firstOrFail());
        $article = Article::factory()->create(['status'=>'submitted']);
        ManuscriptWorkflow::create(['article_id'=>$article->id,'stage'=>'initial_check','data'=>[]]);
        $submission = Submission::create(['article_id'=>$article->id,'submitted_by_id'=>$article->created_by_id,'round'=>1,'status'=>'pending','submitted_at'=>now()]);
        $this->actingAs($admin)->get(route('admin.submissions.show',$submission))->assertOk()
            ->assertSee('Continue workflow / update status')->assertDontSee('name="status"',false);
        $this->put(route('admin.submissions.update',$submission),['status'=>'accepted'])
            ->assertRedirect(route('workflow.show',$article))->assertSessionHasNoErrors();
        $this->assertSame('pending',$submission->fresh()->status->value);
        $this->assertSame('initial_check',$article->fresh()->workflow->stage);
    }
}
