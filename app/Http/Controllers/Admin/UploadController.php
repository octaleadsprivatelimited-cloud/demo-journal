<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Services\UploadManager;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->hasAnyRole('admin', 'super-admin'), 403);
    }

    public function index(Request $request, UploadManager $uploads)
    {
        $this->authorizeAdmin($request);
        $article = $request->filled('article') ? Article::withTrashed()->findOrFail($request->integer('article')) : null;
        return view('admin.uploads', ['entries' => $uploads->entries($article), 'article' => $article]);
    }

    public function store(Request $request, UploadManager $uploads)
    {
        $this->authorizeAdmin($request);
        $data = $request->validate(['article_id' => ['required', 'exists:articles,id'], 'purpose' => ['required', 'in:pdf,image,supplementary,manuscript,cover_letter,response'], 'file' => ['required', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp,svg', 'max:20480']]);
        $article = Article::findOrFail($data['article_id']);
        $file = $request->file('file');
        if (in_array($data['purpose'], ['pdf', 'image'])) {
            $request->validate(['file' => [$data['purpose'] === 'pdf' ? 'mimes:pdf' : 'mimes:jpg,jpeg,png,webp,svg']]);
            [, $field, $disk] = UploadManager::TYPES[$data['purpose']];
            if ($article->$field) {
                $uploads->change($data['purpose'], $article, $file);
            } else {
                $path = $file->store('articles/uploads', $disk);
                abort_unless($path, 503);
                $article->update([$field => $path]);
            }
        } else {
            $path = $file->store('workflow/'.$article->id, 'local');
            abort_unless($path, 503);
            \App\Models\WorkflowFile::create(['article_id' => $article->id, 'uploaded_by_id' => $request->user()->id, 'purpose' => $data['purpose'], 'path' => $path, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(), 'checksum' => hash_file('sha256', $file->getRealPath()), 'size' => $file->getSize(), 'round' => \App\Models\WorkflowFile::where('article_id', $article->id)->max('round') ?? 0]);
        }
        return back()->with('success', 'File uploaded.');
    }

    public function update(Request $request, string $type, int $id, UploadManager $uploads)
    {
        $this->authorizeAdmin($request);
        $record = $uploads->record($type, $id);
        $image = in_array($type, ['image', 'category', 'author', 'user', 'issue', 'editorial', 'indexing']) || ($type === 'media' && $record->visibility === 'public');
        $request->validate(['file' => ['required', 'file', $image ? 'mimes:jpg,jpeg,png,webp,svg' : ($type === 'pdf' ? 'mimes:pdf' : 'mimes:pdf,doc,docx,jpg,jpeg,png,webp,svg'), 'max:20480']]);
        $uploads->change($type, $record, $request->file('file'));
        return back()->with('success', 'File replaced. The previous file was removed from storage.');
    }

    public function destroy(Request $request, string $type, int $id, UploadManager $uploads)
    {
        $this->authorizeAdmin($request);
        $uploads->change($type, $uploads->record($type, $id), null);
        return back()->with('success', 'File permanently removed from storage and its references cleared. You can upload a new file.');
    }
}
