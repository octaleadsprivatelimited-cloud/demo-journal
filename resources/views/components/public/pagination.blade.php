@props(['paginator'])
@if($paginator->hasPages())
    <nav class="pagination" aria-label="Pagination">
        @if($paginator->onFirstPage())
            <span class="pagination-arrow is-disabled"><x-public.icon name="arrow-left" /> Previous</span>
        @else
            <a class="pagination-arrow" href="{{ $paginator->previousPageUrl() }}" rel="prev"><x-public.icon name="arrow-left" /> Previous</a>
        @endif
        <div class="pagination-pages">
            @foreach($paginator->getUrlRange(max(1, $paginator->currentPage() - 2), min($paginator->lastPage(), $paginator->currentPage() + 2)) as $page => $url)
                @if($page === $paginator->currentPage())
                    <span aria-current="page">{{ $page }}</span>
                @else
                    <a href="{{ $url }}">{{ $page }}</a>
                @endif
            @endforeach
        </div>
        @if($paginator->hasMorePages())
            <a class="pagination-arrow" href="{{ $paginator->nextPageUrl() }}" rel="next">Next <x-public.icon name="arrow-right" /></a>
        @else
            <span class="pagination-arrow is-disabled">Next <x-public.icon name="arrow-right" /></span>
        @endif
    </nav>
@endif
