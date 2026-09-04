<?php

namespace Tests\Feature;

use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToWriteFile;
use Tests\TestCase;

class FailureRecoveryTest extends TestCase
{
    public function test_oversized_requests_get_actionable_html_and_json_responses(): void
    {
        Route::post('/api/failure-size', fn () => throw new PostTooLargeException);
        Route::post('/failure-size', fn () => throw new PostTooLargeException);
        $this->postJson('/api/failure-size')->assertStatus(413)->assertJsonPath('code', 'upload_too_large');
        $this->post('/failure-size')->assertStatus(413)->assertSee('32 MB')->assertSee('20 MB');
    }

    public function test_storage_failure_does_not_expose_internal_paths(): void
    {
        Route::post('/api/failure-storage', fn () => throw UnableToWriteFile::atLocation('/private/research/secret.pdf', 'Internal storage details'));
        $this->postJson('/api/failure-storage')->assertStatus(503)
            ->assertJsonPath('code', 'storage_unavailable')->assertDontSee('secret.pdf')->assertDontSee('Internal storage details');
    }

    public function test_error_pages_render_without_database_or_vite_manifest(): void
    {
        DB::shouldReceive('connection')->never();
        foreach (['404', '419', '500', '503', '413', 'storage'] as $code) {
            $html = view('errors.'.$code)->render();
            $this->assertStringContainsString('Return home', $html);
            $this->assertStringNotContainsString('/build/', $html);
        }
    }

    public function test_real_failed_write_throws_instead_of_returning_a_false_path(): void
    {
        $root = tempnam(sys_get_temp_dir(), 'unwritable-root-');
        unlink($root);
        mkdir($root);
        mkdir($root.'/paper.pdf');
        try {
            $disk = Storage::build(array_merge(config('filesystems.disks.local'), ['root' => $root]));
            $this->expectException(UnableToWriteFile::class);
            $disk->put('paper.pdf', '%PDF-1.4 document');
        } finally {
            rmdir($root.'/paper.pdf');
            rmdir($root);
        }
    }

    public function test_partial_upload_returns_field_error_and_does_not_reach_controller(): void
    {
        Route::middleware(\App\Http\Middleware\OptimizeImageUploads::class)->post('/api/failure-partial', function () {
            $this->fail('Invalid upload reached the controller.');
        });
        $path = tempnam(sys_get_temp_dir(), 'partial-upload-');
        try {
            $file = new UploadedFile($path, 'paper.pdf', 'application/pdf', UPLOAD_ERR_PARTIAL, true);
            $this->postJson('/api/failure-partial', ['supplementary' => [$file]])
                ->assertUnprocessable()->assertJsonValidationErrors('supplementary.0');
        } finally {
            unlink($path);
        }
    }
}
