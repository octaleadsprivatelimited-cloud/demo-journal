@extends('layouts.public')

@section('title', 'Contributors')
@section('description', 'Meet the researchers, writers, practitioners, and public thinkers contributing to the journal.')

@section('content')
    <section class="page-hero">
        <div class="container">
            <x-public.breadcrumbs :items="['Contributors' => null]" />
            <div class="page-hero-grid">
                <div><p class="eyebrow">The people behind the ideas</p><h1>Contributors</h1></div>
                <p>Researchers, writers, practitioners, and public thinkers—brought together by intellectual generosity and a commitment to clear ideas.</p>
            </div>
        </div>
    </section>
    <section class="section">
        <div class="container">
            <form class="directory-search" action="{{ route('authors.index') }}" method="get" role="search">
                <label for="author-search">Find a contributor</label>
                <div class="input-with-icon"><x-public.icon name="search" /><input id="author-search" name="q" type="search" value="{{ $term }}" placeholder="Search by name or institution"><button class="button button-primary" type="submit">Search</button></div>
            </form>
            <div class="results-heading"><p><strong>{{ number_format($authors->total()) }}</strong> {{ Str::plural('contributor', $authors->total()) }}</p>@if($term)<a href="{{ route('authors.index') }}">Clear search <x-public.icon name="close" /></a>@endif</div>
            @if($authors->isNotEmpty())
                <div class="author-directory">@foreach($authors as $author)<x-public.author-card :author="$author" />@endforeach</div>
                <x-public.pagination :paginator="$authors" />
            @else
                <x-public.empty-state title="No contributors found" message="Try a broader name, role, or institution." :action="route('authors.index')" action-label="View all contributors" />
            @endif
        </div>
    </section>
@endsection
