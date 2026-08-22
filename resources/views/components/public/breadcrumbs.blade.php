@props(['items' => []])
<nav class="breadcrumbs" aria-label="Breadcrumb">
    <ol>
        <li><a href="{{ route('home') }}">Home</a></li>
        @foreach($items as $label => $url)
            <li aria-hidden="true">/</li>
            <li>
                @if($url)<a href="{{ $url }}">{{ $label }}</a>@else<span aria-current="page">{{ $label }}</span>@endif
            </li>
        @endforeach
    </ol>
</nav>
