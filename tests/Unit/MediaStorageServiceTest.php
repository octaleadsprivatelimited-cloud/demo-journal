<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use App\Services\MediaStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaStorageServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_validated_images_are_stored_under_random_names_with_checksums(): void
    {
        Storage::fake('local');
        config()->set('filesystems.default', 'local');
        $user = User::factory()->create();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Z9SAAAAAASUVORK5CYII=', true);
        $file = UploadedFile::fake()->createWithContent('cover.png', $png);

        $media = app(MediaStorageService::class)->store($file, $user, collection: 'featured images');

        Storage::disk('local')->assertExists($media->path);
        $this->assertSame('image/png', $media->mime_type);
        $this->assertSame('public', $media->visibility);
        $this->assertSame('featured-images', $media->collection);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $media->checksum);
    }
}
