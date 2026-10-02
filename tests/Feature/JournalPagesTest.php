<?php

namespace Tests\Feature;

use App\Models\{Article, Role, User};
use App\Services\JournalPages;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalPagesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user=User::factory()->create(['is_active'=>true,'status'=>'active','email_verified_at'=>now()]);
        $user->roles()->attach(Role::where('slug','super-admin')->firstOrFail());
        return $user;
    }

    public function test_all_checklist_pages_resolve_and_footer_is_grouped(): void
    {
        $this->assertCount(50,JournalPages::all());
        $this->get('/')->assertOk()->assertSee('Policies &amp; Peer Review',false)->assertSee('Reviewer Code of Conduct');
        foreach(JournalPages::all() as $slug=>$page) {
            if (!$page['route']) {
                $response=$this->get(JournalPages::url($slug))->assertOk()->assertSee($page['title']);
                $response->assertDontSee('Add the approved steps');
            }
        }
        $this->get('/policies/not-a-page')->assertNotFound();
        $this->get('/policies?q=plagiarism')->assertOk()->assertSee('Plagiarism Policy');
        $this->get('/policies?group=review')->assertOk();
        $this->get('/current-issue')->assertRedirect('/archive');
    }

    public function test_drafts_are_private_until_approved_and_can_be_unpublished(): void
    {
        $admin=$this->admin();
        $this->actingAs($admin)->get('/admin/pages?q=plagiarism')->assertOk();
        $url='/admin/pages/plagiarism';
        $this->put($url,['action'=>'draft','content'=>'Private draft content','version'=>0])->assertSessionHasNoErrors();
        $this->get('/policies/plagiarism')->assertDontSee('Private draft content');
        $this->put($url,['action'=>'publish','content'=>'Approved public policy content','version'=>1])->assertSessionHasErrors('approved');
        $this->put($url,['action'=>'publish','content'=>'Approved public policy content','version'=>1,'approved'=>1])->assertSessionHasNoErrors();
        $this->get('/policies/plagiarism')->assertSee('Approved public policy content');
        $this->put($url,['action'=>'draft','content'=>'New unpublished revision','version'=>2])->assertSessionHasNoErrors();
        $this->get('/policies/plagiarism')->assertSee('Approved public policy content')->assertDontSee('New unpublished revision');
        $this->put($url,['action'=>'draft','content'=>'Stale edit','version'=>1])->assertSessionHasErrors('content');
        $this->put($url,['action'=>'unpublish','version'=>3])->assertSessionHasNoErrors();
        $this->get('/policies/plagiarism')->assertDontSee('Approved public policy content');
    }

    public function test_page_admin_requires_permission_and_html_is_not_executable(): void
    {
        $user=User::factory()->create();
        $this->actingAs($user)->put('/admin/pages/privacy',['action'=>'publish','content'=>'unsafe','approved'=>1,'version'=>0])->assertForbidden();
        $this->actingAs($this->admin())->put('/admin/pages/privacy',['action'=>'publish','content'=>'## Privacy\n<script>alert(1)</script> [Click](javascript:alert(1))','approved'=>1,'version'=>0])->assertSessionHasNoErrors();
        $this->get('/policies/privacy')->assertDontSee('<script>alert(1)</script>',false)->assertDontSee('href="javascript:',false);
    }

    public function test_corrections_archive_excludes_drafts_and_filters_by_notice(): void
    {
        Article::factory()->published()->create(['title'=>'Public correction example','publication_notice'=>'correction']);
        Article::factory()->published()->create(['title'=>'Public retraction example','publication_notice'=>'retraction']);
        Article::factory()->create(['title'=>'Private notice draft','publication_notice'=>'correction','status'=>'draft']);
        $this->get('/corrections-retractions?type=correction')->assertOk()->assertSee('Public correction example')->assertDontSee('Public retraction example')->assertDontSee('Private notice draft');
        $this->get('/corrections-retractions?q=RETRACTION')->assertSee('Public retraction example')->assertDontSee('Public correction example');
    }
}
