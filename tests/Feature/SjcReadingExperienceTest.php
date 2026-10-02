<?php
namespace Tests\Feature;
use App\Models\{Article,Author,Role,User};
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
class SjcReadingExperienceTest extends TestCase
{
 use RefreshDatabase;
 public function test_local_pdf_can_be_read_inline_and_unpublished_files_stay_private(): void
 {
  Storage::fake('local'); Storage::disk('local')->put('articles/test.pdf','%PDF-1.4 test');
  $article=Article::factory()->create(['status'=>'published','published_at'=>now()->subDay(),'article_number'=>'SJC-LEGACY-69','pdf_path'=>'articles/test.pdf','pdf_download_enabled'=>true]);
  $this->get(route('articles.show',$article->slug))->assertOk()->assertSee('article-pdf-reader')->assertSee('Full article');
  $this->get(route('articles.pdf',['slug'=>$article->slug,'inline'=>1]))->assertOk()->assertHeader('Content-Type','application/pdf')->assertHeader('Content-Disposition','inline; filename='.$article->slug.'.pdf');
  $this->get(route('articles.pdf',$article->slug))->assertOk()->assertHeader('Content-Disposition','attachment; filename='.$article->slug.'.pdf');
  $this->get(route('articles.print',$article->slug))->assertRedirect(route('articles.pdf',['slug'=>$article->slug,'inline'=>1]));
  $article->update(['status'=>'draft']);
  $this->get(route('articles.pdf',['slug'=>$article->slug,'inline'=>1]))->assertNotFound();
 }
 public function test_admin_can_update_a_public_author_profile_without_creating_a_login(): void
 {
  $this->seed(RolePermissionSeeder::class);
  $user=User::factory()->create(['is_active'=>true,'status'=>'active','email_verified_at'=>now()]);
  $user->roles()->attach(Role::where('slug','super-admin')->firstOrFail());
  $author=Author::factory()->create(['is_active'=>true,'user_id'=>null]);
  $this->actingAs($user)->put(route('admin.readiness.update',['resource'=>'authors','record'=>$author->id]),['name'=>'Nida Hassan','organization'=>'Holy Family Hospital','is_active'=>1])->assertRedirect();
  $this->get(route('authors.show',$author->fresh()->slug))->assertOk()->assertSee('Holy Family Hospital');
  $this->assertNull($author->fresh()->user_id);
  $this->put(route('admin.readiness.update',['resource'=>'authors','record'=>$author->id]),['name'=>'Nida Hassan','is_active'=>0])->assertRedirect();
  $this->get(route('authors.show',$author->fresh()->slug))->assertNotFound();
 }
}
