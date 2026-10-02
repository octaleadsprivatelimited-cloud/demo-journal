<?php

namespace Tests\Feature\Portal;

use App\Models\{Article, Role, User, WorkflowFile};
use App\Services\UploadManager;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_deletion_removes_disk_object_and_shared_references_and_can_be_reuploaded(): void
    {
        Storage::fake('local');
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create(['status' => 'active', 'is_active' => true, 'email_verified_at' => now()]);
        $admin->roles()->attach(Role::where('slug', 'super-admin')->firstOrFail());
        Storage::disk('local')->put('test.pdf', '%PDF-1.4 demo');
        $article = Article::factory()->create(['pdf_path' => 'test.pdf']);
        $file = WorkflowFile::create(['article_id' => $article->id, 'uploaded_by_id' => $admin->id, 'purpose' => 'supplementary', 'path' => 'test.pdf', 'original_name' => 'test.pdf', 'mime_type' => 'application/pdf', 'checksum' => hash('sha256','demo'), 'size' => 10, 'round' => 0]);
        $this->actingAs($admin)->get('/admin/uploads?article='.$article->id)->assertOk()->assertSee('Delete file permanently');
        $this->delete('/admin/uploads/workflow/'.$file->id)->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing('test.pdf');
        $this->assertNull($article->fresh()->pdf_path);
        $this->assertDatabaseMissing('workflow_files', ['id' => $file->id]);
        $this->post('/admin/uploads', ['article_id' => $article->id, 'purpose' => 'pdf', 'file' => UploadedFile::fake()->create('new.pdf', 10, 'application/pdf')])->assertSessionHasNoErrors();
        Storage::disk('local')->assertExists($article->fresh()->pdf_path);
        $old = $article->fresh()->pdf_path;
        $this->put('/admin/uploads/pdf/'.$article->id, ['file' => UploadedFile::fake()->create('replacement.pdf', 10, 'application/pdf')])->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($old);
        Storage::disk('local')->assertExists($article->fresh()->pdf_path);
    }

    public function test_supplementary_replacement_removes_old_bytes_and_updates_metadata(): void
    {
        Storage::fake('local');
        $article = Article::factory()->create();
        Storage::disk('local')->put('old.docx', 'old document');
        $record = WorkflowFile::create(['article_id' => $article->id, 'uploaded_by_id' => $article->created_by_id, 'purpose' => 'supplementary', 'path' => 'old.docx', 'original_name' => 'old.docx', 'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'checksum' => hash('sha256', 'old document'), 'size' => 12, 'round' => 0]);
        app(UploadManager::class)->change('workflow', $record, UploadedFile::fake()->create('revised.pdf', 10, 'application/pdf'));
        Storage::disk('local')->assertMissing('old.docx');
        Storage::disk('local')->assertExists($record->fresh()->path);
        $this->assertSame('revised.pdf', $record->fresh()->original_name);
        app(UploadManager::class)->change('workflow', $record->fresh(), null);
        $this->assertDatabaseMissing('workflow_files', ['id' => $record->id]);
    }

    public function test_non_admin_cannot_delete_uploads(): void
    {
        $user = User::factory()->create();
        $article = Article::factory()->create(['pdf_path' => 'protected.pdf']);
        $this->actingAs($user)->delete('/admin/uploads/pdf/'.$article->id)->assertForbidden();
    }
}
