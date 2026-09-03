<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ArticleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ArticleActionRequest;
use App\Http\Requests\Admin\ArticleRequest;
use App\Http\Requests\Admin\BulkArticleRequest;
use App\Models\Article;
use App\Models\Author;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use App\Services\ArticleVersionService;
use App\Services\ArticleWorkflowService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class ArticleController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Article::class);
        $articles = Article::query()->withTrashed()->with(['category:id,name,slug', 'creator:id,name', 'assignedEditor:id,name'])
            ->when($request->string('trashed')->toString() === 'only', fn ($q) => $q->onlyTrashed())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('category'), fn ($q) => $q->where('category_id', $request->integer('category')))
            ->when($request->filled('q'), fn ($q) => $q->search($request->string('q')->toString()))
            ->latest('updated_at')->paginate(20)->withQueryString();

        return view('admin.articles.index', [
            'articles' => $articles, 'statuses' => ArticleStatus::cases(),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Article::class);

        return view('admin.articles.create', $this->formData());
    }

    public function store(ArticleRequest $request, ArticleVersionService $versions): RedirectResponse
    {
        Gate::authorize('create', Article::class);
        $article = DB::transaction(function () use ($request, $versions): Article {
            $article = Article::query()->create($this->articleData($request) + [
                'public_id' => (string) Str::uuid(), 'status' => ArticleStatus::Draft,
                'reading_time_minutes' => $this->readingTime($request->string('content')->toString()),
            ]);
            $article->authors()->sync($request->input('authors', []));
            $article->tags()->sync($request->input('tags', []));
            $this->saveSeo($request, $article);
            $versions->snapshot($article, $request->user(), 'Created by editorial team');

            return $article;
        });

        return redirect()->route('admin.articles.edit', $article)->with('success', 'Editorial draft created.');
    }

    public function show(Article $article): View
    {
        Gate::authorize('view', $article);
        $article->load(['category', 'creator:id,name,email', 'assignedEditor:id,name,email', 'authors', 'tags', 'seoMetadata', 'versions.creator:id,name', 'submissions.version', 'submissions.reviews.reviewer:id,name']);

        return view('admin.articles.show', ['article' => $article,
            'editors' => User::query()->active()->whereHas('roles', fn ($query) => $query->whereIn('slug', ['editor', 'admin', 'super-admin']))->orderBy('name')->get(['id', 'name']),
            'reviewers' => User::query()->active()->whereHas('roles', fn ($query) => $query->whereIn('slug', ['reviewer', 'editor', 'admin', 'super-admin']))->orderBy('name')->get(['id', 'name'])]);
    }

    public function edit(Article $article): View
    {
        Gate::authorize('update', $article);

        return view('admin.articles.edit', $this->formData(['article' => $article->load(['authors:id', 'tags:id', 'seoMetadata'])]));
    }

    public function update(ArticleRequest $request, Article $article, ArticleVersionService $versions): RedirectResponse
    {
        Gate::authorize('update', $article);
        DB::transaction(function () use ($request, $article, $versions): void {
            $article->fill($this->articleData($request) + ['reading_time_minutes' => $this->readingTime($request->string('content')->toString())])->save();
            $article->authors()->sync($request->input('authors', []));
            $article->tags()->sync($request->input('tags', []));
            $this->saveSeo($request, $article);
            $versions->snapshot($article, $request->user(), 'Editorial update');
        });

        return back()->with('success', 'Article and SEO metadata updated.');
    }

    public function destroy(Article $article): RedirectResponse
    {
        Gate::authorize('delete', $article);
        $article->delete();

        return redirect()->route('admin.articles.index')->with('success', 'Article moved to trash.');
    }

    public function restore(string $article): RedirectResponse
    {
        $model = Article::withTrashed()->where('slug', $article)->firstOrFail();
        Gate::authorize('restore', $model);
        $model->restore();

        return redirect()->route('admin.articles.edit', $model)->with('success', 'Article restored as '.$model->status->label().'.');
    }

    public function action(ArticleActionRequest $request, Article $article, ArticleWorkflowService $workflow): RedirectResponse
    {
        $action = $request->string('action')->toString();
        Gate::authorize(match ($action) {
            'publish', 'unpublish', 'schedule' => 'publish',
            'approve', 'reject', 'revision', 'minor_revision', 'major_revision', 'assign_reviewer' => 'transition',
            default => 'update',
        }, $article);

        try {
            match ($action) {
                'feature' => $article->update(['is_featured' => true]), 'unfeature' => $article->update(['is_featured' => false]),
                'trend' => $article->update(['is_trending' => true]), 'untrend' => $article->update(['is_trending' => false]),
                'assign_editor' => $this->assignEditor($article, $request->integer('user_id')),
                'assign_reviewer' => $workflow->assignReviewer($article, User::query()->findOrFail($request->integer('user_id')), $request->user(), $request->filled('due_at') ? CarbonImmutable::parse($request->input('due_at')) : null),
                'approve' => $workflow->decide($article, $request->user(), ArticleStatus::Approved, $request->input('note')),
                'reject' => $workflow->decide($article, $request->user(), ArticleStatus::Rejected, $request->input('note')),
                'revision' => $workflow->decide($article, $request->user(), ArticleStatus::RevisionRequired, $request->input('note')),
                'minor_revision' => $workflow->decide($article, $request->user(), ArticleStatus::MinorRevisionRequested, $request->input('note')),
                'major_revision' => $workflow->decide($article, $request->user(), ArticleStatus::MajorRevisionRequested, $request->input('note')),
                'schedule' => $workflow->transition($article, ArticleStatus::Scheduled, $request->user(), $request->input('note'), CarbonImmutable::parse($request->input('scheduled_for'))),
                'publish' => $workflow->transition($article, ArticleStatus::Published, $request->user(), $request->input('note')),
                'unpublish' => $workflow->transition($article, ArticleStatus::Archived, $request->user(), $request->input('note')),
            };
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['action' => $exception->getMessage()]);
        }

        return back()->with('success', 'Article action completed: '.str($action)->replace('_', ' ')->headline().'.');
    }

    public function bulk(BulkArticleRequest $request, ArticleWorkflowService $workflow): RedirectResponse
    {
        $articles = Article::query()->whereKey($request->input('article_ids'))->get();
        foreach ($articles as $article) {
            Gate::authorize(match ($request->input('action')) {
                'delete' => 'delete',
                'publish' => 'publish',
                default => 'update',
            }, $article);
        }
        $completed = 0;
        $skipped = 0;
        foreach ($articles as $article) {
            try {
                match ($request->input('action')) {
                    'delete' => $article->delete(), 'feature' => $article->update(['is_featured' => true]),
                    'unfeature' => $article->update(['is_featured' => false]), 'trend' => $article->update(['is_trending' => true]),
                    'untrend' => $article->update(['is_trending' => false]), 'category' => $article->update(['category_id' => $request->integer('category_id')]),
                    'publish' => $workflow->transition($article, ArticleStatus::Published, $request->user(), 'Bulk publication action'),
                };
                $completed++;
            } catch (DomainException) {
                $skipped++;
            }
        }

        return back()->with('success', "Bulk action completed for {$completed} article(s)".($skipped ? "; {$skipped} skipped due to workflow state." : '.'));
    }

    /** @param array<string, mixed> $extra */
    private function formData(array $extra = []): array
    {
        return $extra + [
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']), 'tags' => Tag::query()->orderBy('name')->get(['id', 'name']),
            'authors' => Author::query()->active()->orderBy('name')->get(['id', 'name', 'organization']),
            'users' => User::query()->active()->with('roles:id,slug')->orderBy('name')->get(['id', 'name', 'email']),
        ];
    }

    /** @return array<string, mixed> */
    private function articleData(ArticleRequest $request): array
    {
        $data = $request->safe()->except(['authors', 'tags', 'featured_image', 'manuscript_pdf', 'seo_title', 'meta_description', 'canonical_url', 'focus_keywords', 'og_title', 'og_description', 'twitter_card']);
        $data['comments_enabled'] = $request->boolean('comments_enabled');
        $data['pdf_download_enabled'] = $request->boolean('pdf_download_enabled');
        if ($request->hasFile('featured_image')) {
            $data['featured_image_path'] = $request->file('featured_image')->store('articles/images', 'public');
        }
        if ($request->hasFile('manuscript_pdf')) {
            $data['pdf_path'] = $request->file('manuscript_pdf')->store('articles/manuscripts', 'local');
        }

        return $data;
    }

    private function saveSeo(ArticleRequest $request, Article $article): void
    {
        $article->seoMetadata()->updateOrCreate([], $request->only(['seo_title', 'meta_description', 'canonical_url', 'focus_keywords', 'og_title', 'og_description', 'twitter_card']));
    }

    private function readingTime(string $content): int
    {
        return max(1, (int) ceil(str_word_count(strip_tags($content)) / 220));
    }

    private function assignEditor(Article $article, int $userId): bool
    {
        $editor = User::query()->active()->findOrFail($userId);
        if (! $editor->hasAnyRole('editor', 'admin', 'super-admin')) {
            throw new DomainException('The selected user is not eligible to edit articles.');
        }

        return $article->update(['assigned_editor_id' => $editor->getKey()]);
    }
}
