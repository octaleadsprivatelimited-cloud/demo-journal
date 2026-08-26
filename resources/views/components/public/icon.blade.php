@props(['name'])
@php($common = 'width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"')
@switch($name)
    @case('menu') <svg {!! $common !!}><path d="M4 7h16M4 12h16M4 17h16"/></svg> @break
    @case('close') <svg {!! $common !!}><path d="m6 6 12 12M18 6 6 18"/></svg> @break
    @case('search') <svg {!! $common !!}><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg> @break
    @case('user') <svg {!! $common !!}><circle cx="12" cy="8" r="3.5"/><path d="M4 21a8 8 0 0 1 16 0"/></svg> @break
    @case('arrow-right') <svg {!! $common !!}><path d="M5 12h14m-5-5 5 5-5 5"/></svg> @break
    @case('arrow-left') <svg {!! $common !!}><path d="M19 12H5m5-5-5 5 5 5"/></svg> @break
    @case('arrow-up-right') <svg {!! $common !!}><path d="M7 17 17 7M8 7h9v9"/></svg> @break
    @case('calendar') <svg {!! $common !!}><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg> @break
    @case('clock') <svg {!! $common !!}><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg> @break
    @case('eye') <svg {!! $common !!}><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.5"/></svg> @break
    @case('download') <svg {!! $common !!}><path d="M12 3v12m-5-5 5 5 5-5M5 21h14"/></svg> @break
    @case('pdf') <svg {!! $common !!}><path d="M6 2h9l4 4v16H6z"/><path d="M15 2v5h5M9 16h6M9 12h4"/></svg> @break
    @case('print') <svg {!! $common !!}><path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v7H6z"/></svg> @break
    @case('share') <svg {!! $common !!}><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.7 10.6 6.6-4.1m-6.6 6.9 6.6 4.1"/></svg> @break
    @case('quote') <svg {!! $common !!}><path d="M10 11H5a5 5 0 0 1 5-5v12H4v-6m16-1h-5a5 5 0 0 1 5-5v12h-6v-6"/></svg> @break
    @case('check') <svg {!! $common !!}><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/></svg> @break
    @case('alert') <svg {!! $common !!}><path d="M12 3 2.8 20h18.4L12 3Z"/><path d="M12 9v5m0 3h.01"/></svg> @break
    @case('chevron-down') <svg {!! $common !!}><path d="m6 9 6 6 6-6"/></svg> @break
    @case('external') <svg {!! $common !!}><path d="M14 4h6v6m0-6L10 14"/><path d="M20 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h5"/></svg> @break
    @default <svg {!! $common !!}><circle cx="12" cy="12" r="9"/></svg>
@endswitch
