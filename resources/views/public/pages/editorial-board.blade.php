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
            @foreach(['Editors-in-Chief' => $chiefs, 'Editors' => $editors, 'Reviewers, advisors & staff' => $reviewers] as $heading => $members)
                @if($members->isNotEmpty())
                    <section class="editor-group" aria-label="{{ $heading }}">
                        <div class="minor-heading"><h2>{{ $heading }}</h2><span>{{ $members->count() }} {{ Str::plural('member', $members->count()) }}</span></div>
                        <div class="editor-grid">@foreach($members as $member)<x-public.editor-card :member="$member" />@endforeach</div>
                    </section>
                @endif
            @endforeach
            @if($registeredEditors->isNotEmpty())
                <div class="board-group"><div class="minor-heading"><h2>Registered editors</h2></div><div class="author-directory">@foreach($registeredEditors as $person)<x-public.member-card :person="$person" />@endforeach</div></div>
            @endif
            @unless($chiefs->isNotEmpty() || $editors->isNotEmpty() || $reviewers->isNotEmpty() || $registeredEditors->isNotEmpty())
                <x-public.empty-state title="Profiles are being prepared" message="Editorial appointments are managed in the publication workspace and will appear here once published." :action="route('about')" action-label="Read our editorial principles" />
            @endunless
        </div>
    </section>
    <section class="standards-band"><div class="container"><p class="eyebrow light">Editorial independence</p><h2>Judgment guided by evidence, transparency, and care.</h2><p>Editorial decisions are based on relevance, originality, methodological integrity, and clarity. Commercial relationships do not determine publication outcomes.</p><a class="text-link text-link-light" href="{{ route('contact') }}">Ask about our process <x-public.icon name="arrow-right" /></a></div></section>
@endsection
