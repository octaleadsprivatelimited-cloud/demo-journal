@props(['title', 'message', 'action' => null, 'actionLabel' => null])
<div {{ $attributes->class(['empty-state']) }}>
    <span class="empty-state-mark" aria-hidden="true"><x-public.icon name="search" /></span>
    <h2>{{ $title }}</h2>
    <p>{{ $message }}</p>
    @if($action && $actionLabel)<a class="button button-outline" href="{{ $action }}">{{ $actionLabel }} <x-public.icon name="arrow-right" /></a>@endif
</div>
