@props(['href', 'icon' => 'article', 'active' => false])
<a href="{{ $href }}" @class(['portal-nav-link', 'is-active' => $active]) @if($active) aria-current="page" @endif>
    <x-portal.icon :name="$icon" /><span>{{ $slot }}</span>
</a>
