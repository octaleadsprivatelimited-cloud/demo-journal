<?php
namespace Tests\Feature;
use App\Models\{Article,Author,EditorialMember,Role,User};
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class SjcMetadataImportTest extends TestCase
{
 use RefreshDatabase;
 public function test_import_is_draft_only_idempotent_and_creates_no_accounts(): void
 {
  $this->seed(RolePermissionSeeder::class);
  $user=User::factory()->create();$user->roles()->attach(Role::where('slug','super-admin')->firstOrFail());
  $file=tempnam(sys_get_temp_dir(),'sjc-test');
  file_put_contents($file,json_encode(['source'=>'https://sjcjournal.com','failures'=>[],'reviewer_note'=>'No named reviewers.','articles'=>[['source_url'=>'https://sjcjournal.com/journals/69/example/abstract','title'=>'Example article','authors'=>['Priya Sharma','Anil Rao'],'doi'=>'10.1234/example','published_date'=>'Feb 18, 2025']],'editorial_members'=>[['name'=>'Example Editor','role'=>'Editor','institution'=>'Example University']]]));
  try {
   $this->artisan('journal:import-sjc-metadata',['file'=>$file])->assertSuccessful();$this->assertSame(0,Article::count());
   $this->artisan('journal:import-sjc-metadata',['file'=>$file,'--apply'=>true])->assertSuccessful();
   $this->assertSame(1,Article::count());$this->assertSame(2,Author::count());$this->assertSame(1,EditorialMember::count());$this->assertSame(1,User::count());
   $article=Article::first();$this->assertSame('draft',$article->status->value);$this->assertSame(2,$article->authors()->count());$this->assertNull($article->pdf_path);
   $article->update(['title'=>'Edited locally']);
   $this->artisan('journal:import-sjc-metadata',['file'=>$file,'--apply'=>true])->assertSuccessful();
   $this->assertSame(1,Article::count());$this->assertSame('Edited locally',$article->fresh()->title);
  } finally {unlink($file);}
 }
}
