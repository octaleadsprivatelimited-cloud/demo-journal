@props(['article', 'layout' => 'vertical', 'priority' => false, 'showExcerpt' => true])
@php
    $imagePath = $article->featured_image_path;
    $imageUrl = $imagePath
        ? (Illuminate\Support\Str::startsWith($imagePath, ['http://', 'https://']) ? $imagePath : Illuminate\Support\Facades\Storage::disk(config('publication.uploads.disk', 'public'))->url($imagePath))
        : asset('assets/editorial-placeholder.svg');
    $publishedAt = $article->published_at ?? $article->created_at;
    $readingTime = $article->reading_time_minutes ?: max(1, (int) ceil(str_word_count(strip_tags((string) $article->content)) / 220));
@endphp
<article {{ $attributes->class(['article-card', 'article-card-'.$layout]) }}>
    <a class="article-card-image" href="{{ route('articles.show', $article->slug) }}" tabindex="-1" aria-hidden="true">
        <img src="{{ $imageUrl }}" alt="{{ $article->title }}" width="720" height="450" @unless($priority)loading="lazy"@endunless @if($priority)fetchpriority="high"@endif>
        @if($article->is_featured)<span class="image-badge">Editor's selection</span>@endif
    </a>
    <div class="article-card-body">
        <div class="article-kicker">
            @if($article->category)
                <a href="{{ route('categories.show', $article->category->slug) }}">{{ $article->category->name }}</a>
            @else
                <span>Journal</span>
            @endif
            <span aria-hidden="true">•</span>
            <time datetime="{{ optional($publishedAt)->toDateString() }}">{{ optional($publishedAt)->format('M j, Y') }}</time>
        </div>
        <h3><a href="{{ route('articles.show', $article->slug) }}">{{ $article->title }}</a></h3>
        @if($showExcerpt && ($article->excerpt || $article->subtitle))
            <p>{{ Illuminate\Support\Str::limit($article->excerpt ?: $article->subtitle, $layout === 'horizontal' ? 180 : 145) }}</p>
        @endif
        <div class="article-card-meta">
            @if($article->authors->isNotEmpty())
                <span>By {!! $article->authors->take(2)->map(fn($author) => '<a href="'.e(route('authors.show', $author->slug)).'">'.e($author->name).'</a>')->implode(', ') !!}</span>
            @endif
            <span class="reading-time"><x-public.icon name="clock" /> {{ $readingTime }} min read</span>
        </div>
    </div>
</article>
