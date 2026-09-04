@extends('layouts.public')

@section('title', 'Downloads')
@section('description', 'Download available journal articles and reading editions from Singapore Journal of Cardiology.')

@section('content')
    <section class="downloads-hero">
        <div class="container">
            <x-public.breadcrumbs :items="['Downloads' => null]" />
            <p class="eyebrow light">Reading library</p>
            <h1>Take the journal with you.</h1>
            <p>Download available reading editions for offline study, reference, and discussion.</p>
        </div>
    </section>
    <section class="section">
        <div class="container">
            <div class="section-heading"><div><p class="eyebrow">Available files</p><h2>Journal downloads</h2></div><span>{{ $downloads->count() }} available</span></div>
            <div class="downloads-list">
                @forelse($downloads as $article)
                    <article class="download-row">
                        <div class="download-mark">PDF</div>
                        <div><p class="eyebrow">{{ $article->category?->name ?? 'Journal article' }} · {{ $article->published_at?->format('Y') }}</p><h3>{{ $article->title }}</h3><p>{{ $article->excerpt }}</p></div>
                        <a class="button button-primary" href="{{ route('articles.pdf', $article) }}"><x-public.icon name="download" />Download</a>
                    </article>
                @empty
                    <div class="downloads-empty"><h2>Downloads are being prepared.</h2><p>Reading editions will appear here as they become available.</p><a class="text-link" href="{{ route('articles.index') }}">Explore the journal <x-public.icon name="arrow-right" /></a></div>
                @endforelse
            </div>
        </div>
    </section>
@endsection
