@props(['network'])
<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">
    @switch(strtolower($network))
        @case('youtube')
            <path d="M23 7a3 3 0 0 0-2.1-2.1C19 4.4 12 4.4 12 4.4s-7 0-8.9.5A3 3 0 0 0 1 7a31 31 0 0 0-.5 5 31 31 0 0 0 .5 5 3 3 0 0 0 2.1 2.1c1.9.5 8.9.5 8.9.5s7 0 8.9-.5A3 3 0 0 0 23 17a31 31 0 0 0 .5-5 31 31 0 0 0-.5-5ZM9.6 15.7V8.3L16 12Z" />
            @break
        @case('facebook')
            <path d="M14 22v-9h3l.5-3H14V8.1c0-.9.3-1.6 1.7-1.6H18V3.8c-.4-.1-1.8-.3-3.3-.3-3.1 0-5.2 1.9-5.2 5.3V10H6v3h3.5v9Z" />
            @break
        @case('instagram')
            <rect x="3" y="3" width="18" height="18" rx="5" fill="none" stroke="currentColor" stroke-width="2" /><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="2" /><circle cx="17.5" cy="6.5" r="1.2" />
            @break
        @case('linkedin')
            <path d="M4 3a2 2 0 1 0 0 4 2 2 0 0 0 0-4ZM2.3 9H6v12H2.3ZM9 9h3.5v1.7h.1c.5-1 1.7-2 3.7-2 4 0 4.7 2.6 4.7 6V21h-3.7v-5.6c0-1.3 0-3-1.8-3s-2.1 1.4-2.1 2.9V21H9Z" />
            @break
        @case('x')
        @case('twitter')
            <path d="M18.9 2H22l-6.8 7.8L23.2 22h-6.3l-4.9-7.5L5.4 22H2.2l7.8-9L2 2h6.5l4.4 6.8ZM17.8 20h1.7L7.5 4H5.7Z" />
            @break
        @default
            <path d="m9 15 6-6m-7 9H6a4 4 0 0 1 0-8h3m6-4h3a4 4 0 0 1 0 8h-3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
    @endswitch
</svg>
