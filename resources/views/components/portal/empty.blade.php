@props(['title' => 'Nothing here yet', 'message' => 'New records will appear here.', 'action' => null, 'href' => null])
<div class="empty-state"><span><x-portal.icon name="article" :size="28" /></span><h3>{{ $title }}</h3><p>{{ $message }}</p>
    @if($action && $href)<a class="portal-button primary" href="{{ $href }}"><x-portal.icon name="plus" :size="17" />{{ $action }}</a>@endif
</div>
