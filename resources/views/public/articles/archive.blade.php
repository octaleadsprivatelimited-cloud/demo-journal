@extends('layouts.public')
@section('title', 'Archives')
@section('description', 'Explore past cardiology publications by year, volume and issue in the Singapore Journal of Cardiology archive.')
@section('content')
<section class="page-hero"><div class="container">
    <x-public.breadcrumbs :items="['Archives' => null]" />
    <div class="page-hero-grid"><div><p class="eyebrow">Singapore Journal of Cardiology</p><h1>Archives</h1></div><p>Browse published research by year and issue. Articles appear from newest to oldest.</p></div>
</div></section>
<section class="section archive-content"><div class="container">
    @if($years->isNotEmpty())
        <nav class="archive-year-nav" aria-label="Archive years">
            <a href="{{ route('archive.index') }}" @if(!$year) aria-current="page" @endif>All years</a>
            @foreach($years as $archiveYear)<a href="{{ route('archive.index', ['year' => $archiveYear]) }}" @if($year === $archiveYear) aria-current="page" @endif>{{ $archiveYear }}</a>@endforeach
        </nav>
    @endif
    @forelse($archiveYears as $publicationYear)
        @php
            $yearVolumes = $volumes->get($publicationYear, collect());
            $issues = $yearVolumes->flatMap->issues;
            $yearArticles = $articleYears->get($publicationYear, collect());
            $unassignedArticles = $yearArticles->reject(fn($article) => $issues->contains('id', $article->journal_issue_id));
        @endphp
        <section class="archive-year" aria-labelledby="archive-year-{{ $publicationYear }}">
            <div class="minor-heading"><h2 id="archive-year-{{ $publicationYear }}">{{ $publicationYear }}</h2><span>{{ $yearArticles->count() }} {{ Str::plural('article', $yearArticles->count()) }}</span></div>
            @if($issues->isNotEmpty())
                <div class="archive-issues">
                    @foreach($yearVolumes as $volume)
                        @foreach($volume->issues as $issue)
                            <a href="{{ route('archive.issue', $issue) }}">
                                <strong>Issue {{ $issue->number }}{{ $issue->title ? ' — '.$issue->title : '' }}</strong>
                                @if($issue->description)<p>{{ $issue->description }}</p>@endif
                                <small>Volume {{ $volume->number }} · {{ $issue->articles_count }} {{ Str::plural('published article', $issue->articles_count) }}</small>
                                @if(!$issue->articles_count)<small>No published articles are available in this issue yet.</small>@endif
                                @if($issue->publication_date)<time datetime="{{ $issue->publication_date->toDateString() }}">{{ $issue->publication_date->format('F j, Y') }}</time>@endif
                            </a>
                        @endforeach
                    @endforeach
                </div>
            @elseif($yearArticles->isNotEmpty())
                <p class="archive-note">Issue information is not available for {{ $publicationYear }}. Browse the published articles below.</p>
            @else
                <p class="archive-note">No published issues or articles are available for {{ $publicationYear }}.</p>
            @endif
            @if($unassignedArticles->isNotEmpty())
                <ul class="archive-article-list">
                    @foreach($unassignedArticles as $article)
                        <li><a href="{{ route('articles.show', $article->slug) }}">{{ $article->title }}</a><time datetime="{{ $article->published_at->toDateString() }}">{{ $article->published_at->format('F j, Y') }}</time></li>
                    @endforeach
                </ul>
            @endif
        </section>
    @empty
        <x-public.empty-state title="No publications are available yet" message="Published articles and issues will appear here as they become available." />
    @endforelse
</div></section>
@endsection
