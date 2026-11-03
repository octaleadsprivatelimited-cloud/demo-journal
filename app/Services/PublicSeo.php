<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Article;
use App\Models\Setting;
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

    /** @return array{name:string, issn:?string, eissn:?string, source_url:?string} */
    public static function articleJournal(Article $article, array $site): array
    {
        $journal = [
            'name' => (string) data_get($site, 'name', config('app.name')),
            'issn' => data_get($site, 'journal.issn'),
            'eissn' => data_get($site, 'journal.eissn'),
            'source_url' => null,
        ];
        if (config('journal.profile') !== 'sjnr'
            || ! preg_match('/\ASJNR-LEGACY-SJC-([a-f0-9]{20})\z/', (string) $article->article_number, $matches)) {
            return $journal;
        }

        // A legacy-looking article number alone does not establish publication
        // provenance. Bind the private import record to its source URL and PDF.
        $identity = $matches[1];
        $source = Setting::query()->whereKey('import.sjc.article.'.$identity)
            ->where('group', 'imports')->where('is_public', false)->value('value');
        if (! is_array($source) || ($source['id'] ?? null) !== $identity
            || ($source['source_journal'] ?? null) !== 'Singapore Journal of Cardiology'
            || ($source['source_issn'] ?? null) !== '2737-4025'
            || ! is_string($source['slug'] ?? null)
            || ! preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $source['slug'])
            || ($source['source_url'] ?? null) !== 'https://sjcjournal.com/article/'.$source['slug']
            || substr(hash('sha256', '/article/'.$source['slug']), 0, 20) !== $identity
            || ! is_array($source['pdf'] ?? null)
            || ! is_string($source['pdf']['sha256'] ?? null)
            || ! preg_match('/\A[a-f0-9]{64}\z/', $source['pdf']['sha256'])
            || $article->pdf_path !== 'articles/sjc-copy/'.$identity.'-'.$source['pdf']['sha256'].'.pdf') {
            return $journal;
        }

        return [
            'name' => 'Singapore Journal of Cardiology',
            'issn' => '2737-4025',
            'eissn' => null,
            'source_url' => $source['source_url'],
        ];
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
