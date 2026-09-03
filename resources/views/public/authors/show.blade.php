@php
    $avatar = $author->avatar_path
        ? (Illuminate\Support\Str::startsWith($author->avatar_path, ['http://', 'https://']) ? $author->avatar_path : Illuminate\Support\Facades\Storage::disk(config('publication.uploads.disk', 'public'))->url($author->avatar_path))
        : null;
    $structuredData = array_filter([
        '@context' => 'https://schema.org', '@type' => 'Person', 'name' => $author->name,
        'url' => route('authors.show', $author->slug), 'image' => $avatar, 'description' => $author->biography,
        'jobTitle' => $author->designation, 'affiliation' => $author->organization ? ['@type' => 'Organization', 'name' => $author->organization] : null,
    ]);
@endphp
@extends('layouts.public')

@section('title', $author->name)
@section('description', $author->biography ?: 'Read publications and learn more about '.$author->name.', contributor to '.data_get($site, 'name').'.')
@section('canonical', route('authors.show', $author->slug))
@if($avatar) @section('image', $avatar) @endif

@section('content')
    <section class="profile-hero">
        <div class="container">
            <x-public.breadcrumbs :items="['Contributors' => route('authors.index'), $author->name => null]" />
            <div class="profile-grid">
                <div class="profile-portrait">
                    @if($avatar)<img src="{{ $avatar }}" alt="Portrait of {{ $author->name }}" width="520" height="620">@else<span aria-hidden="true">{{ collect(explode(' ', $author->name))->take(2)->map(fn($name) => mb_substr($name, 0, 1))->implode('') }}</span>@endif
                </div>
                <div class="profile-copy">
                    <p class="eyebrow">Contributor profile</p>
                    <div class="author-name-row"><h1>{{ $author->name }}</h1>@if($author->is_verified)<span class="verified"><x-public.icon name="check" /><span class="sr-only">Verified contributor</span></span>@endif</div>
                    @if($author->designation || $author->organization)<p class="profile-role">{{ collect([$author->designation, $author->organization])->filter()->join(' · ') }}</p>@endif
                    @if($author->biography)<div class="profile-bio">{{ $author->biography }}</div>@endif
                    <div class="profile-details">
                        <div><strong>{{ number_format($author->published_articles_count) }}</strong><span>{{ Str::plural('Publication', $author->published_articles_count) }}</span></div>
                        @if($author->website_url)<a href="{{ $author->website_url }}" rel="external noopener" target="_blank">Website <x-public.icon name="external" /></a>@endif
                    </div>
                    @if(collect($author->social_links)->filter()->isNotEmpty())
                        <div class="profile-social" aria-label="{{ $author->name }} social links">
                            @foreach($author->social_links as $network => $url)@if($url)<a href="{{ $url }}" target="_blank" rel="noopener">{{ Str::headline($network) }} <x-public.icon name="arrow-up-right" /></a>@endif @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
    <section class="section">
        <div class="container">
            <div class="section-heading"><div><p class="eyebrow">Selected work</p><h2>Publications by {{ $author->name }}</h2></div></div>
            @if($articles->isNotEmpty())
                <div class="article-grid article-grid-two">@foreach($articles as $article)<x-public.article-card :article="$article" layout="horizontal" />@endforeach</div>
                <x-public.pagination :paginator="$articles" />
            @else
                <x-public.empty-state title="No published work yet" message="This contributor's approved publications will appear here." :action="route('articles.index')" action-label="Explore the journal" />
            @endif
        </div>
    </section>
@endsection
