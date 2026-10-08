<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Str;

final class PublicSeo
{
    public static function description(?string $value, int $limit = 165): string
    {
        $plain = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plain = trim(preg_replace('/\s+/u', ' ', $plain) ?? '');

        return Str::limit($plain, $limit, '…');
    }

    public static function articleDescription(Article $article, string $journal): string
    {
        foreach ([$article->seoMetadata?->meta_description, $article->excerpt, $article->abstract] as $candidate) {
            if (filled($candidate) && trim($candidate) !== 'Read the complete published article, including its figures, tables and references.') {
                return self::description($candidate);
            }
        }

        // A title-based summary is factual even when the original abstract is unavailable.
        return self::description($article->title.'. Published in '.$journal.'.');
    }

    public static function paginated(): bool
    {
        return request()->routeIs('articles.index', 'journals.index', 'archive.*', 'authors.*', 'people.*', 'categories.show', 'policies.index', 'corrections.index', 'search');
    }

    public static function canonical(string $url): string
    {
        $page = request()->query('page');
        if (self::paginated() && is_scalar($page) && ctype_digit((string) $page) && (int) $page > 1) {
            return $url.(str_contains($url, '?') ? '&' : '?').'page='.(int) $page;
        }

        return $url;
    }

    public static function filteredListing(): bool
    {
        if (! request()->routeIs('articles.index', 'journals.index', 'archive.index', 'authors.index', 'people.index', 'policies.index', 'corrections.index')) {
            return false;
        }

        return collect(request()->query())
            ->except(['page', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid'])
            ->contains(fn ($value): bool => filled($value));
    }
}
