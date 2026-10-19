@extends('layouts.public')

@section('title', 'Cardiology Research')
@section('description', data_get($site, 'description'))

@section('content')
    <section class="home-intro">
        <div class="container">
            <h1 class="home-title">Singapore Journal of Cardiology</h1>
            <p class="home-research-summary">Explore open access cardiology research, clinical case reports and reviews in cardiovascular medicine.</p>
            <div class="issue-line">
                @if($latest->isNotEmpty())<span>Latest publication</span>
                <strong>{{ $latest->first()->published_at->format('F Y') }}</strong>@endif
                <span class="issue-rule"></span>
                <a href="{{ route('archive.index') }}">Browse the archive <x-public.icon name="arrow-right" /></a>
            </div>

            <div class="home-hero-layout home-hero-single">
            @if($heroArticles->isNotEmpty())
                <div class="hero-slider" data-hero-slider role="region" aria-roledescription="carousel" aria-label="Featured and latest articles">
                @foreach($heroArticles as $featured)
                @php
                    $featuredImage = $featured->featured_image_path
                        ? (Illuminate\Support\Str::startsWith($featured->featured_image_path, ['http://', 'https://']) ? $featured->featured_image_path : Illuminate\Support\Facades\Storage::disk(config('publication.uploads.disk', 'public'))->url($featured->featured_image_path))
                        : asset('assets/editorial-placeholder.svg');
                    $featuredDate = $featured->published_at ?? $featured->created_at;
                @endphp
                <article class="lead-story" data-clickable-article data-hero-slide role="group" aria-roledescription="slide" aria-label="{{ $loop->iteration }} of {{ $loop->count }}" @if(!$loop->first) hidden @endif>
                    <div class="lead-story-copy">
                        <p class="eyebrow">{{ $featured->is_featured ? 'Featured article' : 'Latest article' }}</p>
                        @if($featured->category)<a class="category-link" href="{{ route('categories.show', $featured->category->slug) }}">{{ $featured->category->name }}</a>@endif
                        <h2><a href="{{ route('articles.show', $featured->slug) }}">{{ $featured->title }}</a></h2>
                        @if($featured->subtitle || $featured->excerpt)<p class="lead-dek">{{ $featured->subtitle ?: $featured->excerpt }}</p>@endif
                        <div class="lead-meta">
                            @if($featured->authors->isNotEmpty())
                                <span>By {!! $featured->authors->map(fn($author) => '<a href="'.e(route('authors.show', $author->slug)).'">'.e($author->name).'</a>')->implode(', ') !!}</span>
                            @endif
                            <time datetime="{{ optional($featuredDate)->toDateString() }}">{{ optional($featuredDate)->format('F j, Y') }}</time>
                        </div>
                        <a class="text-link" href="{{ route('articles.show', $featured->slug) }}">Read article <x-public.icon name="arrow-right" /></a>
                    </div>
                    <a class="lead-story-image" href="{{ route('articles.show', $featured->slug) }}" tabindex="-1" aria-hidden="true">
                        <img data-image-fallback="{{ asset('assets/editorial-placeholder.svg') }}" src="{{ $featuredImage }}" alt="{{ $featured->title }}" width="900" height="720" loading="{{ $loop->first ? 'eager' : 'lazy' }}">
                        <span class="image-index">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }} / {{ $featured->is_featured ? 'Featured' : 'Latest' }}</span>
                    </a>
                </article>
                @endforeach
                @if($heroArticles->count() > 1)
                    <div class="hero-controls" data-hero-controls hidden>
                        <button type="button" data-hero-prev aria-label="Previous article">←</button>
                        <span data-hero-count aria-live="off">1 / {{ $heroArticles->count() }}</span>
                        <button type="button" data-hero-next aria-label="Next article">→</button>
                        <button type="button" data-hero-pause>Pause slideshow</button>
                    </div>
                @endif
                </div>
            @else
                <div class="editorial-welcome">
                    <p class="eyebrow">Singapore Journal of Cardiology</p>
                    <h2>Research for cardiovascular medicine.</h2>
                    <p>Explore cardiology research, clinical case reports and reviews from the Singapore Journal of Cardiology.</p>
                    <div><a class="button button-primary" href="{{ route('articles.index') }}">Explore the journal</a><a class="button button-ghost" href="{{ route('about') }}">Our editorial mission</a></div>
                </div>
            @endif

            </div>
        </div>
    </section>
    @if($editorSelections->isNotEmpty())
        <section class="section home-selections" aria-labelledby="editor-selections-title">
            <div class="container publication-container">
                <div class="section-heading">
                    <div><p class="eyebrow">Editor's selections</p><h2 id="editor-selections-title">Featured research</h2></div>
                    <a class="text-link" href="{{ route('articles.index') }}">Explore the journal <x-public.icon name="arrow-right" /></a>
                </div>
                <div class="article-grid article-grid-two selection-grid">
                    @foreach($editorSelections as $article)<x-public.article-card :article="$article" />@endforeach
                </div>
            </div>
        </section>
    @endif
    @if($latest->isNotEmpty())
        <section class="section section-latest">
            <div class="container publication-container">
                <div class="section-heading">
                    <div><p class="eyebrow">Recently published</p><h2>Latest articles</h2></div>
                    <a class="text-link" href="{{ route('articles.index') }}">View all publications <x-public.icon name="arrow-right" /></a>
                </div>
                <div class="article-grid article-grid-three perspectives-grid">
                    @foreach($latest as $article)<x-public.article-card :article="$article" />@endforeach
                </div>
            </div>
        </section>
    @endif

    @if($editors->isNotEmpty())
        <section class="section home-editorial" aria-label="Editorial team">
            <div class="container">
                @foreach(['Editors-in-Chief' => $editors->filter(fn($member) => str_contains(strtolower($member->role), 'chief')), 'Editors' => $editors->reject(fn($member) => str_contains(strtolower($member->role), 'chief'))] as $heading => $members)
                    @if($members->isNotEmpty())
                        <section class="editor-group" aria-label="{{ $heading }}">
                            <div class="minor-heading"><h2>{{ $heading }}</h2></div>
                            <div class="editor-grid">@foreach($members as $member)<x-public.editor-card :member="$member" />@endforeach</div>
                        </section>
                    @endif
                @endforeach
                <a class="text-link" href="{{ route('editorial-board') }}">Meet the full editorial board <x-public.icon name="arrow-right" /></a>
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
                        <li data-clickable-article>
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
