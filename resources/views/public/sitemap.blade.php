<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url><loc>{{ route('home') }}</loc><changefreq>daily</changefreq><priority>1.0</priority></url>
    <url><loc>{{ route('articles.index') }}</loc><changefreq>daily</changefreq><priority>0.9</priority></url>
    <url><loc>{{ route('authors.index') }}</loc><changefreq>weekly</changefreq><priority>0.7</priority></url>
    <url><loc>{{ route('categories.index') }}</loc><changefreq>weekly</changefreq><priority>0.7</priority></url>
    <url><loc>{{ route('about') }}</loc><changefreq>monthly</changefreq><priority>0.5</priority></url>
    <url><loc>{{ route('editorial-board') }}</loc><changefreq>monthly</changefreq><priority>0.5</priority></url>
    <url><loc>{{ route('contact') }}</loc><changefreq>yearly</changefreq><priority>0.3</priority></url>
    @foreach($articles as $article)
        <url><loc>{{ route('articles.show', $article->slug) }}</loc><lastmod>{{ optional($article->updated_at ?? $article->published_at)->toAtomString() }}</lastmod><changefreq>monthly</changefreq><priority>0.8</priority></url>
    @endforeach
    @foreach($categories as $category)
        <url><loc>{{ route('categories.show', $category->slug) }}</loc><lastmod>{{ optional($category->updated_at)->toAtomString() }}</lastmod><changefreq>weekly</changefreq><priority>0.6</priority></url>
    @endforeach
    @foreach($authors as $author)
        <url><loc>{{ route('authors.show', $author->slug) }}</loc><lastmod>{{ optional($author->updated_at)->toAtomString() }}</lastmod><changefreq>monthly</changefreq><priority>0.6</priority></url>
    @endforeach
</urlset>
