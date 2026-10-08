@extends('layouts.public')

@section('title', 'Editorial board')
@section('description', 'Meet the editors and reviewers who uphold the journal’s standards of rigor, independence, and clarity.')

@section('content')
    <section class="page-hero">
        <div class="container">
            <x-public.breadcrumbs :items="['Editorial board' => null]" />
            <div class="page-hero-grid"><div><p class="eyebrow">Stewards of the journal</p><h1>Editorial board</h1></div><p>Our editors and reviewers bring distinct disciplines to a shared task: helping ambitious work become precise, generous, and durable.</p></div>
        </div>
    </section>
    <section class="section editorial-board">
        <div class="container">
            @if($chief)
                <div class="board-chief">@if($chief->avatar_path)<img class="board-avatar" src="{{ Illuminate\Support\Str::startsWith($chief->avatar_path, ['http://','https://']) ? $chief->avatar_path : Illuminate\Support\Facades\Storage::disk(config('publication.uploads.disk','public'))->url($chief->avatar_path) }}" alt="Portrait of {{ $chief->name }}" width="120" height="120">@endif<div><p class="eyebrow">Editor-in-Chief</p><h2>{{ $chief->name }}</h2><p>{{ $chief->credentials }}{{ $chief->institution ? ' · '.$chief->institution : '' }}</p>@if($chief->orcid)<a href="https://orcid.org/{{ $chief->orcid }}" rel="external noopener">ORCID</a>@endif</div>@if($chief->biography)<p>{{ $chief->biography }}</p>@endif</div>
            @endif
            @if($editors->isNotEmpty())
                <div class="board-group"><div class="minor-heading"><h2>Editors</h2><span>{{ $editors->count() }} members</span></div><div class="author-directory">@foreach($editors as $member)<article class="author-card"><div class="author-avatar">@if($member->avatar_path)<img src="{{ Illuminate\Support\Str::startsWith($member->avatar_path, ['http://','https://']) ? $member->avatar_path : Illuminate\Support\Facades\Storage::disk('public')->url($member->avatar_path) }}" alt="Portrait of {{ $member->name }}" width="144" height="144" loading="lazy" style="object-fit:cover">@else<span aria-hidden="true">{{ mb_substr($member->name,0,1) }}</span>@endif</div><div><h3>{{ $member->name }}</h3><p>{{ $member->role }}</p><small>{{ collect([$member->credentials,$member->institution,$member->country])->filter()->join(' · ') }}</small>@if($member->orcid)<a href="https://orcid.org/{{ $member->orcid }}" rel="external noopener">ORCID</a>@endif</div></article>@endforeach</div></div>
            @endif
            @if($reviewers->isNotEmpty())
                <div class="board-group"><div class="minor-heading"><h2>Reviewers, advisors &amp; staff</h2><span>{{ $reviewers->count() }} members</span></div><div class="author-directory">@foreach($reviewers as $member)<article class="author-card"><div class="author-avatar">@if($member->avatar_path)<img src="{{ Illuminate\Support\Str::startsWith($member->avatar_path, ['http://','https://']) ? $member->avatar_path : Illuminate\Support\Facades\Storage::disk('public')->url($member->avatar_path) }}" alt="Portrait of {{ $member->name }}" width="144" height="144" loading="lazy" style="object-fit:cover">@else<span aria-hidden="true">{{ mb_substr($member->name,0,1) }}</span>@endif</div><div><h3>{{ $member->name }}</h3><p>{{ $member->role }}</p><small>{{ collect([$member->credentials,$member->institution,$member->country])->filter()->join(' · ') }}</small>@if($member->orcid)<a href="https://orcid.org/{{ $member->orcid }}" rel="external noopener">ORCID</a>@endif</div></article>@endforeach</div></div>
            @endif
            @if($registeredEditors->isNotEmpty())
                <div class="board-group"><div class="minor-heading"><h2>Registered editors</h2></div><div class="author-directory">@foreach($registeredEditors as $person)<x-public.member-card :person="$person" />@endforeach</div></div>
            @endif
            @unless($chief || $editors->isNotEmpty() || $reviewers->isNotEmpty() || $registeredEditors->isNotEmpty())
                <x-public.empty-state title="Profiles are being prepared" message="Editorial appointments are managed in the publication workspace and will appear here once published." :action="route('about')" action-label="Read our editorial principles" />
            @endunless
        </div>
    </section>
    <section class="standards-band"><div class="container"><p class="eyebrow light">Editorial independence</p><h2>Judgment guided by evidence, transparency, and care.</h2><p>Editorial decisions are based on relevance, originality, methodological integrity, and clarity. Commercial relationships do not determine publication outcomes.</p><a class="text-link text-link-light" href="{{ route('contact') }}">Ask about our process <x-public.icon name="arrow-right" /></a></div></section>
@endsection
