@php
    $seo = $category->seoMetadata;
    $description = $seo?->meta_description ?: $category->seo_description ?: $category->description ?: 'Explore publications in '.$category->name.'.';
    $breadcrumbItems = ['Disciplines' => route('categories.index')];
    if ($category->parent) {
        $breadcrumbItems[$category->parent->name] = route('categories.show', $category->parent->slug);
    }
    $breadcrumbItems[$category->name] = null;
@endphp
@extends('layouts.public')

@section('title', $seo?->seo_title ?: $category->seo_title ?: $category->name)
@section('description', $description)
@section('canonical', $seo?->canonical_url ?: route('categories.show', $category->slug))

@section('content')
    <section class="category-hero">
        <div class="container">
            <x-public.breadcrumbs :items="$breadcrumbItems" />
            <div class="category-hero-copy">
                <p class="eyebrow">Discipline</p>
                <h1>{{ $category->name }}</h1>
                @if($category->description)<p>{{ $category->description }}</p>@endif
            </div>
            @if($category->children->isNotEmpty())<div class="topic-pills" aria-label="Sub-disciplines">@foreach($category->children as $child)<a href="{{ route('categories.show', $child->slug) }}">{{ $child->name }}</a>@endforeach</div>@endif
        </div>
    </section>
    <section class="section">
        <div class="container">
            <div class="results-heading"><p><strong>{{ number_format($articles->total()) }}</strong> {{ Str::plural('publication', $articles->total()) }}</p></div>
            @if($articles->isNotEmpty())
                <div class="article-grid article-grid-three">@foreach($articles as $article)<x-public.article-card :article="$article" />@endforeach</div>
                <x-public.pagination :paginator="$articles" />
            @else
                <x-public.empty-state title="No publications in this discipline yet" message="Explore the wider journal while this collection develops." :action="route('articles.index')" action-label="Browse all publications" />
            @endif
        </div>
    </section>
@endsection
