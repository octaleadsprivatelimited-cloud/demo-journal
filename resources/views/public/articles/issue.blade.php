@extends('layouts.public')

@section('title', 'Volume '.$issue->volume->number.', Issue '.$issue->number)
@section('description', $issue->title ?: 'Published articles in this journal issue.')

@section('content')
<section class="section archive-issue">
    <div class="container">
        <x-public.breadcrumbs :items="['Archive' => route('archive.index'), 'Issue '.$issue->number => null]"/>
        <header class="page-heading">
            <p class="eyebrow">{{ $issue->publication_date?->format('F Y') }}</p>
            <div class="issue-heading">
                @if($issue->cover_image_path)
                    <img src="{{ Storage::disk('public')->url($issue->cover_image_path) }}" alt="Cover for Issue {{ $issue->number }}" width="120" height="160" loading="lazy" data-image-fallback="{{ asset('assets/editorial-placeholder.svg') }}">
                @endif
                <div>
                    <h1>Volume {{ $issue->volume->number }}, Issue {{ $issue->number }}</h1>
                    @if($issue->title)
                        <p>{{ $issue->title }}</p>
                    @endif
                    @if($issue->description)
                        <p>{{ $issue->description }}</p>
                    @endif
                </div>
            </div>
        </header>
        <div class="article-grid article-grid-three">
            @forelse($articles as $article)
                <x-public.article-card :article="$article"/>
            @empty
                <x-public.empty-state title="No published articles" message="This issue does not contain any published articles yet."/>
            @endforelse
        </div>
        <x-public.pagination :paginator="$articles"/>
    </div>
</section>
@endsection
