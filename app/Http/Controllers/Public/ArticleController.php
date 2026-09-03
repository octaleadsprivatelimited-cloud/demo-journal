<?php

namespace App\Http\Controllers\Public;

use App\Http\Requests\Public\ArticleIndexRequest;
use App\Models\Article;
use App\Models\ArticleView;
use App\Models\Author;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Tag;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ArticleController extends PublicController
{
    public function index(ArticleIndexRequest $request): View
    {
        return $this->listing($request, 'articles');
    }

    public function journals(ArticleIndexRequest $request): View
    {
        return $this->listing($request, 'journals');
    }

    public function show(Request $request, string $slug): View
    {
        $site = $this->siteSettings();
        $article = $this->findPublishedArticle($slug);

        $this->recordView($request, $article);

        $publishedAt = $article->published_at ?? $article->created_at;

        $previous = $this->publishedArticles()
            ->where('published_at', '<', $publishedAt)
            ->latest('published_at')
            ->first();

        $next = $this->publishedArticles()
            ->where('published_at', '>', $publishedAt)
            ->oldest('published_at')
            ->first();

        $tagIds = $article->tags->modelKeys();
        $related = $this->publishedArticles()
            ->whereKeyNot($article->getKey())
            ->where(function ($query) use ($article, $tagIds): void {
                if ($article->category_id) {
                    $query->where('category_id', $article->category_id);
                }

                if ($tagIds !== []) {
                    $method = $article->category_id ? 'orWhereHas' : 'whereHas';
                    $query->{$method}('tags', fn ($tagQuery) => $tagQuery->whereKey($tagIds));
                }
            })
            ->latest('published_at')
            ->limit(3)
            ->get();

        $comments = collect();
        if ((bool) data_get($site, 'features.comments', false) && $article->comments_enabled) {
            $comments = Comment::query()
                ->where('article_id', $article->getKey())
                ->whereNull('parent_id')
                ->approved()
                ->with([
                    'user:id,name,profile_image_path',
                    'replies' => fn ($query) => $query
                        ->approved()
                        ->with('user:id,name,profile_image_path')
                        ->oldest('approved_at'),
                ])
                ->oldest('approved_at')
                ->limit(100)
                ->get();
        }

        return view('public.articles.show', $this->publicViewData(compact(
            'article',
            'previous',
            'next',
            'related',
            'comments',
            'site',
        )));
    }

    public function print(string $slug): View
    {
        $article = $this->findPublishedArticle($slug);

        return view('public.articles.print', $this->publicViewData(compact('article')));
    }

    public function pdf(string $slug): RedirectResponse|Response
    {
        $article = $this->findPublishedArticle($slug);

        abort_unless($this->featureEnabled('pdf_downloads') && $article->pdf_download_enabled && filled($article->pdf_path), 404);

        if (Str::startsWith($article->pdf_path, ['https://', 'http://'])) {
            return redirect()->away($article->pdf_path);
        }

        abort_unless(Storage::disk('local')->exists($article->pdf_path), 404);

        return Storage::disk('local')->download(
            $article->pdf_path,
            Str::slug($article->title).'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }

    private function listing(ArticleIndexRequest $request, string $mode): View
    {
        $filters = $request->validated();
        $query = $this->publishedArticles();

        $query
            ->when($filters['q'] ?? null, fn ($builder, $term) => $builder->search($term))
            ->when($filters['category'] ?? null, fn ($builder, $category) => $builder->whereHas(
                'category',
                fn ($categoryQuery) => $categoryQuery->where('slug', $category),
            ))
            ->when($filters['tag'] ?? null, fn ($builder, $tag) => $builder->whereHas(
                'tags',
                fn ($tagQuery) => $tagQuery->where('slug', $tag),
            ))
            ->when($filters['author'] ?? null, fn ($builder, $author) => $builder->whereHas(
                'authors',
                fn ($authorQuery) => $authorQuery->where('slug', $author),
            ))
            ->when($filters['from'] ?? null, fn ($builder, $date) => $builder->whereDate('published_at', '>=', $date))
            ->when($filters['to'] ?? null, fn ($builder, $date) => $builder->whereDate('published_at', '<=', $date));

        match ($filters['sort'] ?? 'newest') {
            'oldest' => $query->oldest('published_at'),
            'popular' => $query->orderByDesc('view_count')->latest('published_at'),
            'title' => $query->orderBy('title'),
            default => $query->latest('published_at'),
        };

        $articles = $query->paginate(12)->withQueryString();

        $featured = $this->publishedArticles()
            ->featured()
            ->latest('published_at')
            ->limit(3)
            ->get();

        $categories = Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'slug']);
        $tags = Tag::query()->whereHas('articles', fn ($query) => $query->published())->orderBy('name')->limit(40)->get(['id', 'name', 'slug']);
        $authors = Author::query()->where('is_active', true)->whereHas('articles', fn ($query) => $query->published())->orderBy('name')->get(['id', 'name', 'slug']);

        return view('public.articles.index', $this->publicViewData(compact(
            'articles',
            'featured',
            'categories',
            'tags',
            'authors',
            'filters',
            'mode',
        )));
    }

    private function findPublishedArticle(string $slug): Article
    {
        return $this->publishedArticles()
            ->with(['seoMetadata', 'media'])
            ->where('slug', $slug)
            ->firstOrFail();
    }

    private function recordView(Request $request, Article $article): void
    {
        try {
            if (! class_exists(ArticleView::class) || ! Schema::hasTable('article_views')) {
                return;
            }

            $sessionId = $request->hasSession() ? $request->session()->getId() : null;
            $ipHash = $request->ip()
                ? hash_hmac('sha256', $request->ip(), (string) config('app.key'))
                : null;

            $alreadyViewed = ArticleView::query()
                ->where('article_id', $article->getKey())
                ->where('viewed_at', '>=', now()->subHours(12))
                ->where(function ($query) use ($sessionId, $ipHash): void {
                    if ($sessionId) {
                        $query->where('session_id', $sessionId);
                    } elseif ($ipHash) {
                        $query->where('ip_hash', $ipHash);
                    } else {
                        $query->whereRaw('1 = 0');
                    }
                })
                ->exists();

            if ($alreadyViewed) {
                return;
            }

            ArticleView::query()->create([
                'article_id' => $article->getKey(),
                'user_id' => $request->user()?->getKey(),
                'session_id' => $sessionId,
                'ip_hash' => $ipHash,
                'referrer' => Str::limit((string) $request->headers->get('referer'), 2048, ''),
                'user_agent' => Str::limit((string) $request->userAgent(), 1024, ''),
                'viewed_at' => now(),
            ]);

            Article::query()->whereKey($article->getKey())->increment('view_count');
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
