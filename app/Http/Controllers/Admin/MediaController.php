<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MediaRequest;
use App\Models\Media;
use App\Services\MediaStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MediaController extends Controller
{
    public function __construct(private readonly MediaStorageService $storage) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Media::class);

        return view('admin.media.index', ['mediaItems' => Media::query()->with('uploadedBy:id,name')->when($request->filled('q'), function ($q) use ($request): void {
            $term = '%'.addcslashes($request->string('q')->toString(), '%_').'%';
            $q->where(fn ($sub) => $sub->where('original_name', 'like', $term)->orWhere('alt_text', 'like', $term));
        })->when($request->filled('type'), fn ($q) => $q->where('mime_type', 'like', $request->string('type')->toString().'/%'))->latest()->paginate(24)->withQueryString()]);
    }

    public function store(MediaRequest $request): RedirectResponse
    {
        Gate::authorize('create', Media::class);
        $file = $request->file('file');
        $isImage = str_starts_with((string) $file->getMimeType(), 'image/');
        $media = $this->storage->store($file, $request->user(), null, $request->input('collection', 'library'), $isImage ? 'public' : 'local', $isImage ? 'public' : 'private');
        $media->update(['alt_text' => $request->input('alt_text'), 'caption' => $request->input('caption')]);

        return back()->with('success', 'Media uploaded to the library.');
    }

    public function edit(Media $medium): View
    {
        Gate::authorize('update', $medium);

        return view('admin.media.edit', ['media' => $medium]);
    }

    public function download(Media $medium): StreamedResponse
    {
        Gate::authorize('view', $medium);
        abort_unless(Storage::disk($medium->disk)->exists($medium->path), 404);

        return Storage::disk($medium->disk)->download($medium->path, $medium->original_name);
    }

    public function update(MediaRequest $request, Media $medium): RedirectResponse
    {
        Gate::authorize('update', $medium);
        $data = $request->safe()->except('file');
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $isImage = str_starts_with((string) $file->getMimeType(), 'image/');
            $replacement = $this->storage->store($file, $request->user(), null, $request->input('collection', $medium->collection), $isImage ? 'public' : 'local', $isImage ? 'public' : 'private');
            $replacement->update($data);
            $this->storage->delete($medium);

            return redirect()->route('admin.media.edit', $replacement)->with('success', 'Media file replaced and metadata updated.');
        } else {
            $medium->update($data);
        }

        return back()->with('success', 'Media metadata updated.');
    }

    public function destroy(Media $medium): RedirectResponse
    {
        Gate::authorize('delete', $medium);
        $this->storage->delete($medium);

        return redirect()->route('admin.media.index')->with('success', 'Media file deleted.');
    }
}
