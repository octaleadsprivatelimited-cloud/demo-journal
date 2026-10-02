<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url><loc>{{ $sitemapBaseUrl }}</loc><changefreq>daily</changefreq><priority>1.0</priority></url>
    <url><loc>{{ $sitemapBaseUrl }}/articles</loc><changefreq>daily</changefreq><priority>0.9</priority></url>
    <url><loc>{{ $sitemapBaseUrl }}/journals</loc><changefreq>weekly</changefreq></url>
    <url><loc>{{ $sitemapBaseUrl }}/resources</loc><changefreq>monthly</changefreq></url>
    <url><loc>{{ $sitemapBaseUrl }}/authors</loc><changefreq>weekly</changefreq><priority>0.7</priority></url>
    <url><loc>{{ $sitemapBaseUrl }}/categories</loc><changefreq>weekly</changefreq><priority>0.7</priority></url>
    <url><loc>{{ $sitemapBaseUrl }}/downloads</loc><changefreq>weekly</changefreq><priority>0.6</priority></url>
    <url><loc>{{ $sitemapBaseUrl }}/about</loc><changefreq>monthly</changefreq><priority>0.5</priority></url>
    <url><loc>{{ $sitemapBaseUrl }}/editorial-board</loc><changefreq>monthly</changefreq><priority>0.5</priority></url>
    <url><loc>{{ $sitemapBaseUrl }}/contact</loc><changefreq>yearly</changefreq><priority>0.3</priority></url>
    <url><loc>{{ $sitemapBaseUrl }}/policies</loc><changefreq>monthly</changefreq></url>
    <url><loc>{{ $sitemapBaseUrl }}/archive</loc><changefreq>weekly</changefreq></url>
    <url><loc>{{ $sitemapBaseUrl }}/corrections-retractions</loc><changefreq>weekly</changefreq></url>
    @foreach(\App\Services\JournalPages::all() as $slug=>$page)
        @if(!$page['route'] && \App\Services\JournalPages::state($slug)['published'])<url><loc>{{ $sitemapBaseUrl }}/policies/{{ $slug }}</loc><changefreq>monthly</changefreq></url>@endif
    @endforeach
    @foreach($articles as $article)
        <url><loc>{{ $sitemapBaseUrl }}/article/{{ $article->slug }}</loc><lastmod>{{ optional($article->updated_at ?? $article->published_at)->toAtomString() }}</lastmod><changefreq>monthly</changefreq><priority>0.8</priority></url>
    @endforeach
    @foreach($categories as $category)
        <url><loc>{{ $sitemapBaseUrl }}/category/{{ $category->slug }}</loc><lastmod>{{ optional($category->updated_at)->toAtomString() }}</lastmod><changefreq>weekly</changefreq><priority>0.6</priority></url>
    @endforeach
    @foreach($authors as $author)
        <url><loc>{{ $sitemapBaseUrl }}/author/{{ $author->slug }}</loc><lastmod>{{ optional($author->updated_at)->toAtomString() }}</lastmod><changefreq>monthly</changefreq><priority>0.6</priority></url>
    @endforeach
</urlset>
