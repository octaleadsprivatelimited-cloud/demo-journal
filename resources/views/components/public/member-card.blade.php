@props(['person'])
<article class="author-card">
    <a class="author-avatar" href="{{ route('people.show', $person) }}" tabindex="-1">
        <img src="{{ $person->profile_image_url }}" alt="Portrait of {{ $person->name }}" width="144" height="144" loading="lazy" style="object-fit:cover">
    </a>
    <div>
        <h3><a href="{{ route('people.show', $person) }}">{{ $person->name }}</a></h3>
        @if($person->designation || $person->organization)
            <p>{{ collect([$person->designation, $person->organization])->filter()->join(' · ') }}</p>
        @endif
    </div>
</article>
