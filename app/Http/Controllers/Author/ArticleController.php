<?php

declare(strict_types=1);

namespace App\Http\Controllers\Author;

use App\Enums\ArticleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Author\ArticleRequest;
use App\Models\Article;
use App\Models\Author;
use App\Models\Category;
use App\Models\Tag;
use App\Services\ArticleVersionService;
use App\Services\MediaStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class ArticleController extends Controller
{
    public function __construct(private readonly MediaStorageService $mediaStorage) {}

    public function index(Request $request): View
    {
        $articles = Article::query()
            ->where('created_by_id', $request->user()->getKey())
            ->with(['category:id,name,slug', 'reviews' => fn ($query) => $query->whereNotNull('completed_at')->latest('completed_at')])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('q'), fn ($query) => $query->search($request->string('q')->toString()))
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('author.articles.index', ['articles' => $articles, 'statuses' => ArticleStatus::cases()]);
    }

    public function create(): View
    {
        Gate::authorize('create', Article::class);

        return view('author.articles.create', $this->formData());
    }

    public function store(ArticleRequest $request): RedirectResponse
    {
        Gate::authorize('create', Article::class);
        $data = $this->articleData($request);

        $article = DB::transaction(function () use ($request, $data): Article {
            $article = Article::query()->create($data + [
                'public_id' => (string) Str::uuid(),
                'created_by_id' => $request->user()->getKey(),
                'content' => $data['content'] ?? '',
                'status' => ArticleStatus::Draft,
                'reading_time_minutes' => $this->readingTime((string) ($data['content'] ?? '')),
            ]);

            $this->syncAuthors($request, $article);
            $article->tags()->sync($request->input('tags', []));
            $this->storeSupportingDocuments($request, $article);

            return $article;
        });

        return redirect()->route('author.articles.edit', $article)->with('success', 'Draft created. Autosave is now active.');
    }

    public function show(Request $request, Article $article): View
    {
        Gate::authorize('view', $article);
        $article->load([
            'category:id,name,slug', 'tags:id,name,slug', 'authors:id,name,slug',
            'versions.creator:id,name',
            'submissions.reviews' => fn ($query) => $query->whereNotNull('completed_at')->with(['reviewer:id,name', 'submission:id,round', 'comments' => fn ($comments) => $comments->where('is_confidential', false)]),
            'media',
        ]);

        return view('author.articles.show', compact('article'));
    }

    public function edit(Request $request, Article $article): View
    {
        Gate::authorize('update', $article);
        abort_unless(in_array($article->status, [ArticleStatus::Draft, ArticleStatus::RevisionRequired, ArticleStatus::Rejected], true), 409, 'This manuscript is locked while the editorial team reviews it.');

        return view('author.articles.edit', $this->formData(['article' => $article->load('tags:id')]));
    }

    public function update(ArticleRequest $request, Article $article, ArticleVersionService $versions): RedirectResponse
    {
        Gate::authorize('update', $article);
        abort_unless(in_array($article->status, [ArticleStatus::Draft, ArticleStatus::RevisionRequired, ArticleStatus::Rejected], true), 409, 'This manuscript is locked while the editorial team reviews it.');
        $data = $this->articleData($request);

        DB::transaction(function () use ($request, $article, $data, $versions): void {
            $article->fill($data + ['reading_time_minutes' => $this->readingTime((string) ($data['content'] ?? ''))])->save();
            $article->tags()->sync($request->input('tags', []));
            $this->syncAuthors($request, $article);
            $this->storeSupportingDocuments($request, $article);
            $versions->snapshot($article, $request->user(), $request->string('change_summary')->trim()->toString() ?: 'Manual draft update');
        });

        return redirect()->route('author.articles.edit', $article)->with('success', 'Draft saved and a version snapshot was created.');
    }

    public function destroy(Request $request, Article $article): RedirectResponse
    {
        Gate::authorize('delete', $article);
        abort_unless(in_array($article->status, [ArticleStatus::Draft, ArticleStatus::Rejected], true), 409, 'Submitted manuscripts cannot be deleted.');
        $article->delete();

        return redirect()->route('author.articles.index')->with('success', 'The manuscript was moved to the archive.');
    }

    /** @param array<string, mixed> $extra */
    private function formData(array $extra = []): array
    {
        return $extra + [
            'categories' => Category::query()->active()->orderBy('name')->get(['id', 'name', 'parent_id']),
            'tags' => Tag::query()->orderBy('name')->get(['id', 'name']),
            'coAuthors' => Author::query()->active()->where('user_id', '!=', request()->user()->getKey())->orderBy('name')->get(['id', 'name', 'organization']),
        ];
    }

    /** @return array<string, mixed> */
    private function articleData(ArticleRequest $request): array
    {
        $data = $request->safe()->except(['tags', 'co_authors', 'featured_image', 'manuscript_pdf', 'supporting_documents', 'change_summary']);
        if ($request->hasFile('featured_image')) {
            $data['featured_image_path'] = $request->file('featured_image')->store('articles/images', 'public');
        }
        if ($request->hasFile('manuscript_pdf')) {
            $data['pdf_path'] = $request->file('manuscript_pdf')->store('articles/manuscripts', 'local');
        }

        return $data;
    }

    private function readingTime(string $content): int
    {
        return max(1, (int) ceil(str_word_count(strip_tags($content)) / 220));
    }

    private function storeSupportingDocuments(ArticleRequest $request, Article $article): void
    {
        foreach ($request->file('supporting_documents', []) as $file) {
            $this->mediaStorage->store($file, $request->user(), $article, 'supporting_documents', 'local', 'private');
        }
    }

    private function syncAuthors(ArticleRequest $request, Article $article): void
    {
        $owner = $request->user()->author()->first();
        if (! $owner) {
            return;
        }
        $eligible = Author::query()->active()->whereKey($request->input('co_authors', []))->whereKeyNot($owner->getKey())->pluck('id')->values();
        $sync = [$owner->getKey() => ['is_corresponding' => true, 'sort_order' => 0]];
        foreach ($eligible as $index => $authorId) {
            $sync[$authorId] = ['is_corresponding' => false, 'sort_order' => $index + 1];
        }
        $article->authors()->sync($sync);
    }
}
