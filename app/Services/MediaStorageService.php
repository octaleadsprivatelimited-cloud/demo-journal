<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class MediaStorageService
{
    /** @var array<string, string> */
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    ];

    public function store(
        UploadedFile $file,
        User $uploader,
        ?Model $mediable = null,
        string $collection = 'default',
        ?string $disk = null,
        ?string $visibility = null,
    ): Media {
        if (! $file->isValid()) {
            throw new InvalidArgumentException('The uploaded file is not valid.');
        }

        $mimeType = (string) $file->getMimeType();
        $extension = self::MIME_EXTENSIONS[$mimeType] ?? null;
        $maxBytes = (int) config('publication.uploads.max_kilobytes', 25 * 1024) * 1024;

        if (! $extension) {
            throw new InvalidArgumentException('This file type is not allowed.');
        }

        if ((int) $file->getSize() > $maxBytes) {
            throw new InvalidArgumentException('The uploaded file exceeds the maximum allowed size.');
        }

        $disk ??= (string) config('filesystems.default', 'local');
        $isImage = str_starts_with($mimeType, 'image/');
        $visibility ??= $isImage ? 'public' : 'private';

        if (! in_array($visibility, ['public', 'private'], true) || ($visibility === 'public' && ! $isImage)) {
            throw new InvalidArgumentException('Only validated image files may be stored publicly.');
        }

        $publicId = (string) Str::uuid();
        $directory = 'media/'.now()->format('Y/m');
        $filename = $publicId.'.'.$extension;
        $path = Storage::disk($disk)->putFileAs($directory, $file, $filename, ['visibility' => $visibility]);

        if (! $path) {
            throw new RuntimeException('The media file could not be stored.');
        }

        $media = new Media([
            'public_id' => $publicId,
            'uploaded_by_id' => $uploader->getKey(),
            'disk' => $disk,
            'path' => $path,
            'original_name' => Str::limit($file->getClientOriginalName(), 255, ''),
            'mime_type' => $mimeType,
            'extension' => $extension,
            'size_bytes' => (int) $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
            'collection' => Str::slug($collection) ?: 'default',
            'visibility' => $visibility,
        ]);

        if ($mediable) {
            $media->mediable()->associate($mediable);
        }

        $media->save();

        return $media;
    }

    public function delete(Media $media): void
    {
        Storage::disk($media->disk)->delete($media->path);
        $media->delete();
    }
}
