<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ErrorPopupTest extends TestCase
{
    public function test_image_over_five_mb_returns_a_field_specific_code(): void
    {
        Route::middleware(\App\Http\Middleware\OptimizeImageUploads::class)->post('/api/popup-upload', fn () => response()->json(['ok' => true]));
        $file = UploadedFile::fake()->createWithContent('figure.svg', '<svg xmlns="http://www.w3.org/2000/svg"><desc>'.str_repeat('x', 5 * 1024 * 1024).'</desc></svg>');
        $this->postJson('/api/popup-upload', ['image' => $file])->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonPath('error_codes.image.0', 'image_too_large')
            ->assertSee('5 MB');
    }

    public function test_unknown_server_errors_have_safe_codes_even_when_debug_is_on(): void
    {
        config(['app.debug' => true]);
        Route::get('/api/popup-failure', fn () => throw new \RuntimeException('private database password information'));
        Route::get('/popup-failure', fn () => throw new \RuntimeException('private database password information'));
        $this->getJson('/api/popup-failure')->assertStatus(500)->assertJsonPath('code', 'server_error')
            ->assertDontSee('private database password information')->assertHeader('X-Error-Code', 'server_error');
        $this->get('/popup-failure')->assertStatus(500)->assertSee('data-journal-errors', false)
            ->assertSee('server_error')->assertDontSee('private database password information');
    }

    public function test_standard_errors_include_codes_and_popup_markup(): void
    {
        foreach ([403 => 'access_denied', 409 => 'version_conflict', 419 => 'session_expired', 429 => 'rate_limit_exceeded'] as $status => $code) {
            Route::get('/popup-'.$status, fn () => abort($status));
            $this->get('/popup-'.$status)->assertStatus($status)->assertSee('data-journal-errors', false)->assertSee($code);
            $this->getJson('/popup-'.$status)->assertStatus($status)->assertJsonPath('code', $code);
        }
    }

    public function test_popup_encodes_untrusted_validation_messages(): void
    {
        $html = view('components.error-popup', ['popupErrors' => [['code' => 'validation_failed', 'message' => '</script><img src=x onerror=alert(1)>']]])->render();
        $this->assertStringNotContainsString('</script><img', $html);
        $this->assertStringContainsString('\\u003C', $html);
    }
}
