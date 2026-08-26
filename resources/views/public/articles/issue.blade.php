@extends('layouts.public')

@section('title', 'Volume '.$issue->volume->number.', Issue '.$issue->number)
@section('description', $issue->title ?: 'Published articles in this journal issue.')

@section('content')
<main class="section">
    <div class="container">
        <x-public.breadcrumbs :items="['Archive' => route('archive.index'), 'Issue '.$issue->number => null]"/>
        <header class="page-heading">
            <p class="eyebrow">{{ $issue->publication_date?->format('F Y') }}</p>
            <div style="display:flex;gap:1.5rem;align-items:flex-start">
                @if($issue->cover_image_path)
                    <img src="{{ Storage::disk('public')->url($issue->cover_image_path) }}" alt="Cover for Issue {{ $issue->number }}" style="width:120px;height:160px;object-fit:cover">
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
</main>
@endsection
