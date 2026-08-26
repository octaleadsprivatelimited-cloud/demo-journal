@extends('layouts.public')

@section('description', data_get($site, 'description'))

@section('content')
    <section class="home-intro">
        <div class="container">
            <div class="issue-line">
                <span>Current edition</span>
                <strong>{{ now()->format('F Y') }}</strong>
                <span class="issue-rule"></span>
                <a href="{{ route('articles.index') }}">Browse the archive <x-public.icon name="arrow-right" /></a>
            </div>

            @if($featured)
                @php
                    $featuredImage = $featured->featured_image_path
                        ? (Illuminate\Support\Str::startsWith($featured->featured_image_path, ['http://', 'https://']) ? $featured->featured_image_path : Illuminate\Support\Facades\Storage::disk(config('publication.uploads.disk', 'public'))->url($featured->featured_image_path))
                        : asset('assets/editorial-placeholder.svg');
                    $featuredDate = $featured->published_at ?? $featured->created_at;
                @endphp
                <article class="lead-story">
                    <div class="lead-story-copy">
                        <p class="eyebrow">Featured inquiry</p>
                        @if($featured->category)<a class="category-link" href="{{ route('categories.show', $featured->category->slug) }}">{{ $featured->category->name }}</a>@endif
                        <h1><a href="{{ route('articles.show', $featured->slug) }}">{{ $featured->title }}</a></h1>
                        @if($featured->subtitle || $featured->excerpt)<p class="lead-dek">{{ $featured->subtitle ?: $featured->excerpt }}</p>@endif
                        <div class="lead-meta">
                            @if($featured->authors->isNotEmpty())
                                <span>By {!! $featured->authors->map(fn($author) => '<a href="'.e(route('authors.show', $author->slug)).'">'.e($author->name).'</a>')->implode(', ') !!}</span>
                            @endif
                            <time datetime="{{ optional($featuredDate)->toDateString() }}">{{ optional($featuredDate)->format('F j, Y') }}</time>
                        </div>
                        <a class="text-link" href="{{ route('articles.show', $featured->slug) }}">Read the full essay <x-public.icon name="arrow-right" /></a>
                    </div>
                    <a class="lead-story-image" href="{{ route('articles.show', $featured->slug) }}" tabindex="-1" aria-hidden="true">
                        <img src="{{ $featuredImage }}" alt="{{ $featured->title }}" width="900" height="720" fetchpriority="high">
                        <span class="image-index">01 / Featured</span>
                    </a>
                </article>
            @else
                <div class="editorial-welcome">
                    <p class="eyebrow">A journal of ideas &amp; inquiry</p>
                    <h1>Serious thinking for a world in motion.</h1>
                    <p>We publish rigorous, accessible work across research, culture, technology, and public life—made for readers who value depth over velocity.</p>
                    <div><a class="button button-primary" href="{{ route('articles.index') }}">Explore the journal</a><a class="button button-ghost" href="{{ route('about') }}">Our editorial mission</a></div>
                </div>
            @endif
        </div>
    </section>

    @if($latest->isNotEmpty())
        <section class="section section-latest">
            <div class="container">
                <div class="section-heading">
                    <div><p class="eyebrow">Recently published</p><h2>New perspectives</h2></div>
                    <a class="text-link" href="{{ route('articles.index') }}">View all publications <x-public.icon name="arrow-right" /></a>
                </div>
                <div class="latest-grid">
                    <div class="latest-primary">
                        <x-public.article-card :article="$latest->first()" layout="vertical" />
                    </div>
                    <div class="latest-secondary">
                        @foreach($latest->slice(1, 4) as $article)
                            <x-public.article-card :article="$article" layout="horizontal" />
                        @endforeach
                    </div>
                </div>
                @if($latest->count() > 5)
                    <div class="article-grid article-grid-two bordered-grid">
                        @foreach($latest->slice(5) as $article)<x-public.article-card :article="$article" />@endforeach
                    </div>
                @endif
            </div>
        </section>
    @endif

    <section class="manifesto-band">
        <div class="container manifesto-grid">
            <p class="eyebrow light">The journal's premise</p>
            <blockquote>“The most useful ideas are rarely the loudest. They are the ones we are still turning over days later.”</blockquote>
            <a href="{{ route('about') }}">How we publish <x-public.icon name="arrow-up-right" /></a>
        </div>
    </section>

    @if($trending->isNotEmpty())
        <section class="section">
            <div class="container">
                <div class="section-heading">
                    <div><p class="eyebrow">Reader attention</p><h2>Most read this month</h2></div>
                    <span class="section-note">Essays finding their way into the wider conversation</span>
                </div>
                <ol class="trending-list">
                    @foreach($trending as $article)
                        <li>
                            <span class="trend-number">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                            <div>
                                <span class="article-kicker">{{ $article->category?->name ?? 'Journal' }}</span>
                                <h3><a href="{{ route('articles.show', $article->slug) }}">{{ $article->title }}</a></h3>
                                <p>{{ $article->authors->pluck('name')->join(', ') }}</p>
                            </div>
                            <span class="trend-views"><x-public.icon name="eye" /> {{ number_format($article->view_count) }}</span>
                            <a class="circle-link" href="{{ route('articles.show', $article->slug) }}" aria-label="Read {{ $article->title }}"><x-public.icon name="arrow-up-right" /></a>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>
    @endif

    @if($categories->isNotEmpty())
        <section class="section section-tinted">
            <div class="container">
                <div class="section-heading">
                    <div><p class="eyebrow">Browse the archive</p><h2>Disciplines &amp; fields</h2></div>
                    <a class="text-link" href="{{ route('categories.index') }}">All disciplines <x-public.icon name="arrow-right" /></a>
                </div>
                <div class="category-grid">
                    @foreach($categories as $category)<x-public.category-card :category="$category" :index="$loop->iteration" />@endforeach
                </div>
            </div>
        </section>
    @endif

    @if($authors->isNotEmpty())
        <section class="section">
            <div class="container">
                <div class="section-heading">
                    <div><p class="eyebrow">Meet the thinkers</p><h2>Featured contributors</h2></div>
                    <a class="text-link" href="{{ route('authors.index') }}">View all contributors <x-public.icon name="arrow-right" /></a>
                </div>
                <div class="author-grid">@foreach($authors as $author)<x-public.author-card :author="$author" />@endforeach</div>
            </div>
        </section>
    @endif

@endsection
