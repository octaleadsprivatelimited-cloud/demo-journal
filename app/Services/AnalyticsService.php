<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Article;
use App\Models\ArticleView;
use App\Models\SearchLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    /** Public, anonymous reader traffic only. Never expose account or manuscript data. */
    public static function publicTracking(Request $request): array
    {
        if (! app()->environment('production') || ! config('publication.integrations.tracking_enabled')
            || ! $request->isMethod('GET') || $request->user()
            || $request->getHost() !== config('publication.integrations.tracking_host')
            || ! $request->routeIs('home', 'journals.index', 'articles.index', 'articles.show', 'archive.*',
                'categories.*', 'authors.*', 'about', 'resources', 'editorial-board', 'downloads', 'policies.*')) {
            return [];
        }
        // Search terms, tokens, email addresses and arbitrary query parameters are never tracked.
        foreach ($request->query() as $key => $value) {
            if (! is_string($value) || ! in_array($key, ['page', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'], true)
                || ! preg_match('/^[a-zA-Z0-9_-]{1,80}$/D', $value)
                || ($key === 'page' && ! ctype_digit($value))) {
                return [];
            }
        }
        $ga = (string) config('publication.integrations.analytics_id');
        $clarity = (string) config('publication.integrations.clarity_id');
        $ids = array_filter([
            'ga' => preg_match('/^G-[A-Z0-9]{6,20}$/D', $ga) ? $ga : null,
            'clarity' => preg_match('/^[a-z0-9]{6,20}$/D', $clarity) ? $clarity : null,
        ]);

        $campaign = [];
        foreach (['source', 'medium', 'campaign', 'content', 'term'] as $part) {
            $key = $part === 'campaign' ? 'campaign_name' : 'campaign_'.$part;
            if ($request->filled('utm_'.$part)) {
                $campaign[$key] = $request->query('utm_'.$part);
            }
        }

        return $ids ? [...$ids, 'page' => $request->url(), 'campaign' => $campaign] : [];
    }

    public function recordArticleView(
        Article $article,
        ?User $user = null,
        ?string $sessionId = null,
        ?string $ipAddress = null,
        ?string $referrer = null,
        ?string $userAgent = null,
    ): ?ArticleView {
        $ipHash = $ipAddress ? hash_hmac('sha256', $ipAddress, (string) config('app.key')) : null;
        $visitor = $user?->getKey() ? 'u:'.$user->getKey() : ($sessionId ? 's:'.$sessionId : 'i:'.$ipHash);
        $dedupeKey = 'article-view:'.$article->getKey().':'.hash('sha256', $visitor);

        if (! Cache::add($dedupeKey, true, now()->addMinutes(30))) {
            return null;
        }

        return DB::transaction(function () use ($article, $user, $sessionId, $ipHash, $referrer, $userAgent): ArticleView {
            $view = $article->views()->create([
                'user_id' => $user?->getKey(),
                'session_id' => $sessionId,
                'ip_hash' => $ipHash,
                'referrer' => $referrer,
                'user_agent' => $userAgent,
                'viewed_at' => now(),
            ]);

            Article::query()->whereKey($article->getKey())->increment('view_count');

            return $view;
        });
    }

    /** @return Collection<int, Article> */
    public function popularArticles(int $days = 30, int $limit = 10): Collection
    {
        return Article::query()
            ->published()
            ->withCount(['views as recent_views_count' => fn ($query) => $query->where('viewed_at', '>=', now()->subDays($days))])
            ->orderByDesc('recent_views_count')
            ->limit(max(1, min($limit, 100)))
            ->get();
    }

    /** @return array<string, int> */
    public function summary(int $days = 30): array
    {
        $since = now()->subDays($days);

        return [
            'views' => ArticleView::query()->where('viewed_at', '>=', $since)->count(),
            'unique_visitors' => ArticleView::query()->where('viewed_at', '>=', $since)->whereNotNull('ip_hash')->distinct('ip_hash')->count('ip_hash'),
            'searches' => SearchLog::query()->where('searched_at', '>=', $since)->count(),
            'published_articles' => Article::query()->published()->where('published_at', '>=', $since)->count(),
        ];
    }
}
