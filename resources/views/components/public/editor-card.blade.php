@props(['member'])
@php
    $photo = $member->avatar_path;
    $placeholder = asset('assets/editor-placeholder.svg');
    $photoUrl = $photo
        ? (Str::startsWith($photo, ['http://', 'https://']) ? $photo : Storage::disk(config('publication.uploads.disk', 'public'))->url($photo))
        : $placeholder;
@endphp
<article class="editor-card">
    <img class="editor-portrait" src="{{ $photoUrl }}" data-image-fallback="{{ $placeholder }}" alt="{{ $photo ? 'Portrait of '.$member->name : 'Portrait unavailable for '.$member->name }}" width="160" height="200" loading="lazy" decoding="async">
    <div class="editor-details">
        <h3>{{ $member->name }}</h3>
        <p class="editor-role">{{ $member->designation }}</p>
        @if($member->credentials)<p>{{ $member->credentials }}</p>@endif
        @if($member->organization)<p>{{ $member->organization }}</p>@endif
        @if($member->country)<p>{{ $member->country }}</p>@endif
        @if($member->biography)<p class="editor-biography">{{ $member->biography }}</p>@endif
        @if($member->orcid)<a href="https://orcid.org/{{ $member->orcid }}" rel="external noopener">ORCID profile <x-public.icon name="arrow-up-right" /></a>@endif
    </div>
</article>
