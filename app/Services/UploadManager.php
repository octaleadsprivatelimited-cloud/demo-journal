<?php

namespace App\Services;

use App\Models\{Article, Author, Category, EditorialMember, IndexingService, JournalIssue, Media, Review, User, WorkflowFile};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class UploadManager
{
    public const TYPES = [
        'pdf' => [Article::class, 'pdf_path', 'local'],
        'image' => [Article::class, 'featured_image_path', 'public'],
        'workflow' => [WorkflowFile::class, 'path', 'local'],
        'media' => [Media::class, 'path', null],
        'review' => [Review::class, 'review_file_path', 'local'],
        'category' => [Category::class, 'image_path', 'public'],
        'author' => [Author::class, 'avatar_path', 'public'],
        'user' => [User::class, 'profile_image_path', 'public'],
        'issue' => [JournalIssue::class, 'cover_image_path', 'public'],
        'editorial' => [EditorialMember::class, 'photo_path', 'public'],
        'indexing' => [IndexingService::class, 'logo_path', 'public'],
    ];

    public function record(string $type, int $id): Model
    {
        abort_unless(isset(self::TYPES[$type]), 404);
        $class = self::TYPES[$type][0];
        $query = $class::query();
        if (in_array($type, ['pdf', 'image', 'media', 'category', 'author'])) {
            $query->withTrashed();
        }
        return $query->findOrFail($id);
    }

    public function entries(?Article $article = null)
    {
        $entries = collect();
        foreach (self::TYPES as $type => [$class, $field, $disk]) {
            $query = $class::query()->whereNotNull($field)->where($field, '!=', '');
            if (in_array($type, ['pdf', 'image', 'category', 'author'])) {
                $query->withTrashed();
            }
            if ($article) {
                if (in_array($type, ['pdf', 'image'])) {
                    $query->whereKey($article->id);
                } elseif (in_array($type, ['workflow', 'review'])) {
                    $query->where('article_id', $article->id);
                } elseif ($type === 'media') {
                    $query->where('mediable_type', $article->getMorphClass())->where('mediable_id', $article->id);
                } else {
                    continue;
                }
            }
            foreach ($query->get() as $record) {
                $entries->push(['type' => $type, 'id' => $record->id, 'name' => $record->original_name ?: basename($record->$field), 'label' => $record->title ?? $record->name ?? $record->purpose ?? $type]);
            }
        }
        return $entries;
    }

    public function change(string $type, Model $record, ?UploadedFile $file): void
    {
        [, $field, $disk] = self::TYPES[$type];
        $disk ??= $record->disk;
        if ($file && ($disk === 'public' || ($type === 'media' && $record->visibility === 'public'))) {
            abort_unless(str_starts_with((string) $file->getMimeType(), 'image/'), 422, 'Public image uploads must be replaced with an image.');
        }
        $old = $record->$field;
        abort_unless(filled($old), 404);
        if ($file && $disk === 'local' && Article::withTrashed()->where('pdf_path', $old)->exists()) {
            abort_unless($file->getMimeType() === 'application/pdf', 422, 'A public reading PDF must be replaced with a PDF.');
        }
        $new = $file?->store('uploads/replacements', $disk);
        if ($file && ! $new) {
            throw new RuntimeException('Replacement upload failed.');
        }
        try {
            DB::transaction(function () use ($type, $record, $field, $disk, $old, $new, $file) {
                // A workflow manuscript may also be the public article PDF.
                // Keep every structured reference to the same physical object in sync.
                foreach (self::TYPES as $kind => [$class, $column, $storage]) {
                    if ($storage !== null && $storage !== $disk) {
                        continue;
                    }
                    $query = $class::query()->where($column, $old);
                    if (in_array($kind, ['pdf', 'image', 'media', 'category', 'author'])) {
                        $query->withTrashed();
                    }
                    if ($storage === null) {
                        $query->where('disk', $disk);
                    }
                    foreach ($query->lockForUpdate()->get() as $reference) {
                        if (! $file && in_array($kind, ['media', 'workflow'])) {
                            $kind === 'media' ? $reference->forceDelete() : $reference->delete();
                        } else {
                            $data = [$column => $new];
                            if ($file && in_array($kind, ['media', 'workflow'])) {
                                $data += ['original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(), 'checksum' => hash_file('sha256', $file->getRealPath()), $kind === 'media' ? 'size_bytes' : 'size' => $file->getSize()];
                                if ($kind === 'media') {
                                    $data['extension'] = $file->guessExtension();
                                }
                            }
                            $reference->forceFill($data)->save();
                        }
                    }
                }
                if ($old && Storage::disk($disk)->exists($old) && ! Storage::disk($disk)->delete($old)) {
                    throw new RuntimeException('The stored file could not be deleted. No database changes were saved.');
                }
            });
        } catch (\Throwable $error) {
            if ($new) {
                Storage::disk($disk)->delete($new);
            }
            throw $error;
        }
    }
}
