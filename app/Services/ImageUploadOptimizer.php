<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

class ImageUploadOptimizer
{
    public const MAX_BYTES = 1000000;

    public const MAX_INPUT_BYTES = 5 * 1024 * 1024;

    public function optimize(UploadedFile $file, string $field, ?float $deadline = null): void
    {
        $fail = fn (string $message) => throw ValidationException::withMessages([$field => $message]);
        if (! $file->isValid()) {
            $fail('[upload_incomplete] The file upload failed or exceeds the server upload limit.');
        }
        $mime = $file->getMimeType();
        $svg = $mime === 'image/svg+xml' || strtolower($file->getClientOriginalExtension()) === 'svg';
        if (! $svg && ! str_starts_with((string) $mime, 'image/')) {
            return;
        }
        if (filesize($file->getPathname()) > self::MAX_INPUT_BYTES) {
            $fail('[image_too_large] Image upload failed: the original image must be 5 MB or smaller. Select a smaller image and try again.');
        }
        if ($svg) {
            if (filesize($file->getPathname()) > self::MAX_BYTES) {
                $fail('[svg_too_large] SVG files must be no larger than 1 MB. Vector data is preserved without rasterization.');
            }
            $this->validateSvg($file->getPathname(), $fail);
        } elseif (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            $fail('[image_format_unsupported] Supported images are JPG, PNG, WebP and self-contained SVG.');
        } else {
            $size = @getimagesize($file->getPathname());
            if (! $size || $size[0] * $size[1] > 40000000) {
                $fail('[image_invalid] The image is invalid or exceeds the 40-megapixel processing limit.');
            }
            $command = match ($mime) {
                'image/jpeg' => ['jpegoptim', '--preserve', '--quiet', $file->getPathname()],
                'image/png' => ['optipng', '-o2', '-quiet', '-preserve', $file->getPathname()],
                default => null,
            };
            if ($command) {
                $process = new Process($command);
                $remaining = $deadline === null ? 15 : min(15, $deadline - microtime(true));
                if ($remaining <= 0) {
                    $fail('[image_processing_timeout] Image processing took too long. Submit fewer images in each batch.');
                }
                $process->setTimeout($remaining);
                try {
                    $process->mustRun();
                } catch (\Throwable) {
                    $fail('[image_compression_failed] Image compression could not finish. Please try a smaller image.');
                }
            }
        }
        clearstatcache(true, $file->getPathname());
        if (filesize($file->getPathname()) > self::MAX_BYTES) {
            $fail('[image_output_too_large] This image cannot fit within 1 MB using lossless compression. Upload a smaller original; pixels, resolution and quality are never reduced automatically.');
        }
    }

    private function validateSvg(string $path, callable $fail): void
    {
        $xml = file_get_contents($path);
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new \DOMDocument;
            if ($xml === '' || stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false || ! $document->loadXML($xml, LIBXML_NONET) || $document->doctype) {
                $fail('[svg_invalid] Upload a valid SVG without document types or external entities.');
            }
            if ($document->documentElement?->localName !== 'svg' || $document->documentElement?->namespaceURI !== 'http://www.w3.org/2000/svg') {
                $fail('[svg_invalid] The SVG document must have a valid SVG root.');
            }
            $allowed = explode(' ', 'svg g defs title desc metadata path rect circle ellipse line polyline polygon text tspan textPath linearGradient radialGradient stop clipPath mask pattern marker symbol use filter feBlend feColorMatrix feComponentTransfer feComposite feConvolveMatrix feDiffuseLighting feDisplacementMap feDistantLight feDropShadow feFlood feFuncA feFuncB feFuncG feFuncR feGaussianBlur feMerge feMergeNode feMorphology feOffset fePointLight feSpecularLighting feSpotLight feTile feTurbulence');
            foreach ($document->getElementsByTagName('*') as $element) {
                if ($element->namespaceURI !== 'http://www.w3.org/2000/svg' || ! in_array($element->localName, $allowed, true)) {
                    $fail('[svg_unsafe] SVG must be self-contained: scripts, CSS, animation, embedded images and HTML are not supported. Export a plain SVG.');
                }
                foreach ($element->attributes as $attribute) {
                    $name = strtolower($attribute->localName);
                    $value = trim($attribute->value);
                    if (str_starts_with($name, 'on') || in_array($name, ['style', 'base'], true) || ($name === 'href' && ! preg_match('/^#[A-Za-z_][A-Za-z0-9_.:-]*$/D', $value)) || preg_match('/[\\\\@]|(?:javascript|data|https?|file):/i', $value) || (stripos($value, 'url') !== false && ! preg_match('/^url\(#[A-Za-z_][A-Za-z0-9_.:-]*\)$/D', $value))) {
                        $fail('[svg_unsafe] SVG contains active content or external references. Export a self-contained plain SVG.');
                    }
                }
            }
            foreach ((new \DOMXPath($document))->query('//processing-instruction()') as $instruction) {
                $fail('[svg_unsafe] SVG processing instructions are not supported. Export a plain SVG.');
            }
            // Keep the validated vector bytes intact: no rasterization or precision loss.
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
