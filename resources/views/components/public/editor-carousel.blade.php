@props(['members', 'heading'])
<section class="editor-slider" data-hero-slider role="region" aria-roledescription="carousel" aria-label="{{ $heading }}">
    <h2>{{ $heading }}</h2>
    @foreach($members as $member)
        <article class="home-editor" data-hero-slide role="group" aria-roledescription="slide" aria-label="{{ $loop->iteration }} of {{ $loop->count }}">
            <div class="home-editor-photo">
                @if($member->photo_path)
                    <img src="{{ Str::startsWith($member->photo_path, ['http://', 'https://']) ? $member->photo_path : Storage::disk('public')->url($member->photo_path) }}" alt="{{ $member->name }}" width="80" height="104" loading="lazy">
                @else
                    <span aria-hidden="true">{{ mb_substr($member->name, 0, 1) }}</span>
                @endif
            </div>
            <div>
                <h3><a href="{{ route('editorial-board') }}">{{ $member->name }}</a></h3>
                @foreach(collect([$member->credentials, $member->institution, $member->country])->filter() as $detail)<p>{{ $detail }}</p>@endforeach
            </div>
        </article>
    @endforeach
    @if($members->count() > 1)
        <div class="hero-controls editor-controls" data-hero-controls hidden>
            <button type="button" data-hero-prev aria-label="Previous {{ $heading }} profile"><x-public.icon name="arrow-left" /></button>
            <span data-hero-count aria-live="off">1 / {{ $members->count() }}</span>
            <button type="button" data-hero-next aria-label="Next {{ $heading }} profile"><x-public.icon name="arrow-right" /></button>
            <button type="button" data-hero-pause>Pause slideshow</button>
        </div>
    @endif
</section>
