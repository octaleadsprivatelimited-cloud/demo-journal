@props(['article', 'layout' => 'vertical', 'priority' => false, 'showExcerpt' => true])
@php
    $imagePath = $article->featured_image_path;
    $imageUrl = $imagePath
        ? (Illuminate\Support\Str::startsWith($imagePath, ['http://', 'https://']) ? $imagePath : Illuminate\Support\Facades\Storage::disk(config('publication.uploads.disk', 'public'))->url($imagePath))
        : asset('assets/editorial-placeholder.svg');
    $publishedAt = $article->published_at ?? $article->created_at;
    $readingTime = $article->reading_time_minutes ?: max(1, (int) ceil(str_word_count(strip_tags((string) $article->content)) / 220));
    $publicPdfEnabled = app(App\Services\PublicationSettings::class)->featureEnabled('pdf_downloads')
        && $article->pdf_download_enabled;
@endphp
<article {{ $attributes->class(['article-card', 'article-card-'.$layout]) }}>
    <a class="article-card-image" href="{{ route('articles.show', $article->slug) }}" tabindex="-1" aria-hidden="true">
        <img data-image-fallback="{{ asset('assets/editorial-placeholder.svg') }}" src="{{ $imageUrl }}" alt="{{ $article->title }}" width="720" height="450" @unless($priority)loading="lazy"@endunless @if($priority)fetchpriority="high"@endif>
        @if($article->is_featured)<span class="image-badge">Editor's selection</span>@endif
    </a>
    <div class="article-card-body">
        <div class="article-kicker">
            @if($article->category)<a href="{{ route('categories.show', $article->category->slug) }}">{{ $article->category->name }}</a>@else<span>Journal</span>@endif
        </div>
        <h3><a class="article-card-primary" href="{{ route('articles.show', $article->slug) }}" title="{{ $article->title }}">{{ $article->title }}</a></h3>
        <div class="article-card-meta">
            @if($article->authors->isNotEmpty())<p class="article-authors">By {!! $article->authors->map(fn($author) => '<a href="'.e(route('authors.show', $author->slug)).'">'.e($author->name).'</a>')->implode(', ') !!}</p>@endif
            <time datetime="{{ optional($publishedAt)->toDateString() }}">{{ optional($publishedAt)->format('F j, Y') }}</time>
        </div>
        @if($showExcerpt && ($article->excerpt || $article->subtitle))<p>{{ Illuminate\Support\Str::limit($article->excerpt ?: $article->subtitle, $layout === 'horizontal' ? 180 : 145) }}</p>@endif
        <div class="article-actions" aria-label="Read {{ $article->title }}">
            <a href="{{ route('articles.show', $article->slug) }}#abstract">Abstract <x-public.icon name="arrow-right" /></a>
            <a href="{{ route('articles.show', $article->slug) }}#full-paper">Full Paper <x-public.icon name="arrow-right" /></a>
            @if($publicPdfEnabled)<a class="article-pdf-icon" href="{{ route('articles.pdf', $article->slug) }}" aria-label="Download {{ $article->title }} as PDF" title="Download PDF"><x-public.icon name="pdf" /><span>PDF</span></a>@endif
        </div>
    </div>
</article>
