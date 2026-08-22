@props(['author'])
@php
    $avatar = $author->avatar_path
        ? (Illuminate\Support\Str::startsWith($author->avatar_path, ['http://', 'https://']) ? $author->avatar_path : Illuminate\Support\Facades\Storage::disk(config('publication.uploads.disk', 'public'))->url($author->avatar_path))
        : null;
    $initials = collect(preg_split('/\s+/', trim($author->name)))->filter()->take(2)->map(fn($part) => mb_substr($part, 0, 1))->implode('');
@endphp
<article {{ $attributes->class(['author-card']) }}>
    <a class="author-avatar" href="{{ route('authors.show', $author->slug) }}" tabindex="-1">
        @if($avatar)<img src="{{ $avatar }}" alt="" width="144" height="144" loading="lazy">@else<span aria-hidden="true">{{ $initials }}</span>@endif
    </a>
    <div>
        <div class="author-name-row">
            <h3><a href="{{ route('authors.show', $author->slug) }}">{{ $author->name }}</a></h3>
            @if($author->is_verified)<span class="verified" title="Verified contributor"><x-public.icon name="check" /><span class="sr-only">Verified contributor</span></span>@endif
        </div>
        @if($author->designation || $author->organization)
            <p>{{ collect([$author->designation, $author->organization])->filter()->join(' · ') }}</p>
        @endif
        @isset($author->published_articles_count)
            <small>{{ trans_choice(':count publication|:count publications', $author->published_articles_count, ['count' => $author->published_articles_count]) }}</small>
        @endisset
    </div>
</article>
