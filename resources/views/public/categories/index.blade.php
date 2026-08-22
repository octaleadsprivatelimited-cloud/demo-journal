@extends('layouts.public')

@section('title', 'Disciplines')
@section('description', 'Explore journal articles across research disciplines, fields, and themes.')

@section('content')
    <section class="page-hero">
        <div class="container">
            <x-public.breadcrumbs :items="['Disciplines' => null]" />
            <div class="page-hero-grid">
                <div><p class="eyebrow">Paths through the archive</p><h1>Disciplines</h1></div>
                <p>Follow a field or cross its boundaries. Each collection brings together rigorous work around a shared body of questions.</p>
            </div>
        </div>
    </section>
    <section class="section">
        <div class="container">
            @if($categories->isNotEmpty())
                <div class="category-directory">
                    @foreach($categories as $category)
                        <article class="category-directory-item">
                            <x-public.category-card :category="$category" :index="$loop->iteration" />
                            @if($category->children->isNotEmpty())
                                <div class="subcategory-links">@foreach($category->children as $child)<a href="{{ route('categories.show', $child->slug) }}">{{ $child->name }} <span>{{ $child->published_articles_count }}</span></a>@endforeach</div>
                            @endif
                        </article>
                    @endforeach
                </div>
            @else
                <x-public.empty-state title="The archive is being organized" message="Disciplines will appear as soon as the first collection is published." :action="route('articles.index')" action-label="Browse all publications" />
            @endif
        </div>
    </section>
@endsection
