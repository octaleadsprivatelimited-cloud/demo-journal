<?php

namespace Tests\Feature;

use App\Http\Middleware\OptimizeImageUploads;
use App\Services\ImageUploadOptimizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ImageUploadOptimizationTest extends TestCase
{
    private array $paths = [];

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            @unlink($path);
        }
        parent::tearDown();
    }

    private function upload(string $bytes, string $name, string $mime): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'image-test-');
        $this->paths[] = $path;
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, $mime, null, true);
    }

    public function test_png_is_compressed_below_one_mb_without_changing_pixels_or_dimensions(): void
    {
        $image = imagecreatetruecolor(800, 800);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 30, 80, 160, 40));
        ob_start();
        imagepng($image, null, 0);
        $bytes = ob_get_clean();
        $file = $this->upload($bytes, 'figure.png', 'image/png');
        $this->assertGreaterThan(1000000, strlen($bytes));
        app(ImageUploadOptimizer::class)->optimize($file, 'figure');
        $this->assertLessThanOrEqual(1000000, filesize($file->getPathname()));
        $decoded = imagecreatefrompng($file->getPathname());
        $this->assertSame(800, imagesx($decoded));
        $this->assertSame(800, imagesy($decoded));
        $this->assertSame(imagecolorsforindex($image, imagecolorat($image, 400, 400)), imagecolorsforindex($decoded, imagecolorat($decoded, 400, 400)));
        imagedestroy($image);
        imagedestroy($decoded);
    }

    public function test_jpeg_keeps_decoded_pixels_and_embedded_comment(): void
    {
        $image = imagecreatetruecolor(120, 120);
        imagefill($image, 0, 0, imagecolorallocate($image, 42, 87, 136));
        ob_start();
        imagejpeg($image, null, 95);
        $bytes = ob_get_clean();
        imagedestroy($image);
        $comment = 'Original research figure metadata';
        $bytes = substr($bytes, 0, 2)."\xff\xfe".pack('n', strlen($comment) + 2).$comment.substr($bytes, 2);
        $file = $this->upload($bytes, 'figure.jpg', 'image/jpeg');
        $before = imagecreatefromstring($bytes);
        app(ImageUploadOptimizer::class)->optimize($file, 'figure');
        $after = imagecreatefromjpeg($file->getPathname());
        $this->assertSame(imagecolorat($before, 60, 60), imagecolorat($after, 60, 60));
        $this->assertStringContainsString($comment, file_get_contents($file->getPathname()));
        imagedestroy($before);
        imagedestroy($after);
    }

    public function test_oversize_lossless_image_is_rejected_instead_of_degraded(): void
    {
        $image = imagecreatetruecolor(700, 700);
        for ($y = 0; $y < 700; $y++) {
            for ($x = 0; $x < 700; $x++) {
                imagesetpixel($image, $x, $y, random_int(0, 16777215));
            }
        }
        ob_start();
        imagepng($image, null, 0);
        $bytes = ob_get_clean();
        imagedestroy($image);
        $this->expectException(ValidationException::class);
        app(ImageUploadOptimizer::class)->optimize($this->upload($bytes, 'noise.png', 'image/png'), 'figure');
    }

    public function test_plain_svg_and_pdf_keep_their_data(): void
    {
        foreach (['figure.svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10" fill="red"/></svg>', 'paper.pdf' => "%PDF-1.4\nexample document"] as $name => $bytes) {
            $file = $this->upload($bytes, $name, str_ends_with($name, '.svg') ? 'image/svg+xml' : 'application/pdf');
            app(ImageUploadOptimizer::class)->optimize($file, 'file');
            $this->assertSame($bytes, file_get_contents($file->getPathname()));
        }
    }

    public function test_active_svg_is_rejected_before_storage(): void
    {
        $file = $this->upload('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'bad.svg', 'image/svg+xml');
        $this->expectException(ValidationException::class);
        app(ImageUploadOptimizer::class)->optimize($file, 'file');
    }

    public function test_web_upload_middleware_enforces_the_limit_on_nested_files(): void
    {
        Route::middleware(['web', OptimizeImageUploads::class])->post('/image-upload-test', fn () => response()->json(['ok' => true]));
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><desc>'.str_repeat('x', 1000000).'</desc></svg>';
        $this->postJson('/image-upload-test', ['supplementary' => [$this->upload($svg, 'large.svg', 'image/svg+xml')]])->assertUnprocessable()->assertJsonValidationErrors('supplementary.0');
    }
}
