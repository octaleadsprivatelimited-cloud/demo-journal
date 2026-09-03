@extends('layouts.public')

@php
    $isJournal = $mode === 'journals';
    $heading = $isJournal ? 'The journal archive' : 'Latest publications';
    $description = $isJournal
        ? 'Explore peer-informed essays, research, commentary, and reviews from across the journal.'
        : 'New essays and research from our community of contributors.';
@endphp
@section('title', $heading)
@section('description', $description)

@section('content')
    <section class="page-hero page-hero-archive">
        <div class="container">
            <x-public.breadcrumbs :items="[$heading => null]" />
            <div class="page-hero-grid">
                <div><p class="eyebrow">Archive &amp; discovery</p><h1>{{ $heading }}</h1></div>
                <p>{{ $description }} Use the filters to follow a question across disciplines, contributors, and time.</p>
            </div>
        </div>
    </section>

    @if($featured->isNotEmpty() && !collect($filters)->filter()->count())
        <section class="featured-strip">
            <div class="container">
                <div class="minor-heading"><h2>Editorial selections</h2><span>{{ now()->format('F Y') }}</span></div>
                <div class="featured-strip-grid">
                    @foreach($featured as $article)<x-public.article-card :article="$article" :show-excerpt="$loop->first" />@endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="section archive-section">
        <div class="container">
            <form class="filter-panel" action="{{ $isJournal ? route('journals.index') : route('articles.index') }}" method="get" data-filter-form>
                <div class="filter-search">
                    <label for="archive-q">Search publications</label>
                    <div class="input-with-icon"><x-public.icon name="search" /><input id="archive-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Title, abstract, keywords…"></div>
                </div>
                <div><label for="archive-category">Discipline</label><select id="archive-category" name="category"><option value="">All disciplines</option>@foreach($categories as $category)<option value="{{ $category->slug }}" @selected(($filters['category'] ?? '') === $category->slug)>{{ $category->name }}</option>@endforeach</select></div>
                <div><label for="archive-author">Contributor</label><select id="archive-author" name="author"><option value="">All contributors</option>@foreach($authors as $author)<option value="{{ $author->slug }}" @selected(($filters['author'] ?? '') === $author->slug)>{{ $author->name }}</option>@endforeach</select></div>
                <div><label for="archive-tag">Topic</label><select id="archive-tag" name="tag"><option value="">All topics</option>@foreach($tags as $tag)<option value="{{ $tag->slug }}" @selected(($filters['tag'] ?? '') === $tag->slug)>{{ $tag->name }}</option>@endforeach</select></div>
                <div><label for="archive-sort">Sort</label><select id="archive-sort" name="sort"><option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>Newest first</option><option value="popular" @selected(($filters['sort'] ?? '') === 'popular')>Most read</option><option value="oldest" @selected(($filters['sort'] ?? '') === 'oldest')>Oldest first</option><option value="title" @selected(($filters['sort'] ?? '') === 'title')>Title A–Z</option></select></div>
                <button class="button button-primary filter-submit" type="submit">Apply filters</button>
            </form>

            <div class="results-heading">
                <p><strong>{{ number_format($articles->total()) }}</strong> {{ Str::plural('publication', $articles->total()) }}</p>
                @if(collect($filters)->filter()->isNotEmpty())<a href="{{ $isJournal ? route('journals.index') : route('articles.index') }}">Clear all filters <x-public.icon name="close" /></a>@endif
            </div>

            @if($articles->isNotEmpty())
                <div class="article-grid article-grid-three">
                    @foreach($articles as $article)<x-public.article-card :article="$article" />@endforeach
                </div>
                <x-public.pagination :paginator="$articles" />
            @else
                <x-public.empty-state title="No publications match those filters" message="Try widening the date range or removing one of the topic filters." :action="$isJournal ? route('journals.index') : route('articles.index')" action-label="Reset the archive" />
            @endif
        </div>
    </section>
@endsection
