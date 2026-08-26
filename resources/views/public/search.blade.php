@extends('layouts.public')

@section('title', 'Search the journal')
@section('description', 'Search the journal archive by title, keyword, contributor, discipline, topic, and publication date.')
@section('robots', $hasSearch ? 'noindex, follow' : 'index, follow')

@section('content')
    <section class="search-page-hero">
        <div class="container container-reading-wide">
            <x-public.breadcrumbs :items="['Search' => null]" />
            <p class="eyebrow">Archive search</p>
            <h1>Follow an idea through the journal.</h1>
            <form class="primary-search" action="{{ route('search') }}" method="get" role="search">
                <label class="sr-only" for="search-q">Search all publications</label>
                <x-public.icon name="search" />
                <input id="search-q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" placeholder="Search by question, keyword, author…" autofocus>
                <button class="button button-accent" type="submit">Search</button>
            </form>
        </div>
    </section>
    <section class="section search-results-section">
        <div class="container search-layout">
            <aside>
                <form class="advanced-filters" action="{{ route('search') }}" method="get">
                    <div class="filter-title"><h2>Refine results</h2>@if($hasSearch)<a href="{{ route('search') }}">Reset</a>@endif</div>
                    <div><label for="advanced-q">Keywords</label><input id="advanced-q" name="q" value="{{ $filters['q'] ?? '' }}"></div>
                    <div><label for="advanced-title">Title contains</label><input id="advanced-title" name="title" value="{{ $filters['title'] ?? '' }}"></div>
                    <div><label for="advanced-author">Contributor</label><select id="advanced-author" name="author"><option value="">Any contributor</option>@foreach($authors as $author)<option value="{{ $author->slug }}" @selected(($filters['author'] ?? '') === $author->slug)>{{ $author->name }}</option>@endforeach</select></div>
                    <div><label for="advanced-category">Discipline</label><select id="advanced-category" name="category"><option value="">Any discipline</option>@foreach($categories as $category)<option value="{{ $category->slug }}" @selected(($filters['category'] ?? '') === $category->slug)>{{ $category->name }}</option>@endforeach</select></div>
                    <div><label for="advanced-tag">Topic</label><select id="advanced-tag" name="tag"><option value="">Any topic</option>@foreach($tags as $tag)<option value="{{ $tag->slug }}" @selected(($filters['tag'] ?? '') === $tag->slug)>{{ $tag->name }}</option>@endforeach</select></div>
                    <div><label for="advanced-year">Publication year</label><input id="advanced-year" name="year" type="number" min="1800" max="2200" value="{{ $filters['year'] ?? '' }}"></div>
                    <div><label for="advanced-volume">Volume</label><input id="advanced-volume" name="volume" value="{{ $filters['volume'] ?? '' }}"></div>
                    <div><label for="advanced-issue">Issue</label><input id="advanced-issue" name="issue" value="{{ $filters['issue'] ?? '' }}"></div>
                    <div><label for="advanced-type">Article type</label><select id="advanced-type" name="publication_type"><option value="">Any type</option>@foreach(['article','research','review','essay','case-study','editorial'] as $type)<option value="{{ $type }}" @selected(($filters['publication_type'] ?? '')===$type)>{{ str($type)->headline() }}</option>@endforeach</select></div>
                    <fieldset><legend>Published between</legend><div class="date-pair"><label>From<input name="from" type="date" value="{{ $filters['from'] ?? '' }}"></label><label>To<input name="to" type="date" value="{{ $filters['to'] ?? '' }}"></label></div></fieldset>
                    <button class="button button-primary button-block" type="submit">Apply search</button>
                </form>
            </aside>
            <div>
                @if($hasSearch)
                    <div class="results-heading">
                        <p><strong>{{ number_format($articles->total()) }}</strong> {{ Str::plural('result', $articles->total()) }}@if($filters['q'] ?? null) for “{{ $filters['q'] }}”@endif</p>
                    </div>
                    @if($articles->isNotEmpty())
                        <div class="search-result-list">@foreach($articles as $article)<x-public.article-card :article="$article" layout="horizontal" />@endforeach</div>
                        <x-public.pagination :paginator="$articles" />
                    @else
                        <x-public.empty-state title="No exact matches" message="Try fewer terms, a broader date range, or search the full text without additional filters." :action="route('search')" action-label="Start a new search" />
                    @endif
                @else
                    <div class="search-start">
                        <p class="eyebrow">Search with precision</p>
                        <h2>Find the work that moves your question forward.</h2>
                        <p>Search across titles, abstracts, article text, authors, keywords, disciplines, and topics. Use the filters to narrow the archive by publication date or contributor.</p>
                        @if($tags->isNotEmpty())<div class="topic-pills"><span>Popular topics</span>@foreach($tags->take(12) as $tag)<a href="{{ route('search', ['tag' => $tag->slug]) }}">{{ $tag->name }}</a>@endforeach</div>@endif
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection
