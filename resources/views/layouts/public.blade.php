<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
@php
    $site = $site ?? [];
    $siteName = data_get($site, 'name', config('app.name', 'octaleads Journal'));
    $pageTitle = trim($__env->yieldContent('title'));
    $pageDescription = trim($__env->yieldContent('description')) ?: data_get($site ?? [], 'description');
    $canonicalUrl = trim($__env->yieldContent('canonical')) ?: url()->current();
    $socialImage = trim($__env->yieldContent('image')) ?: asset('assets/journal-mark.svg');
    $robots = trim($__env->yieldContent('robots')) ?: 'index, follow, max-image-preview:large';
    $fullTitle = $pageTitle ? $pageTitle.' — '.$siteName : $siteName;
    $defaultSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => $siteName,
        'url' => url('/'),
        'description' => $pageDescription,
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => route('search').'?q={search_term_string}',
            'query-input' => 'required name=search_term_string',
        ],
    ];
@endphp
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#173b35">
    <title>{{ $fullTitle }}</title>
    <meta name="description" content="{{ $pageDescription }}">
    <meta name="robots" content="{{ $robots }}">
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:title" content="{{ trim($__env->yieldContent('og_title')) ?: $fullTitle }}">
    <meta property="og:description" content="{{ trim($__env->yieldContent('og_description')) ?: $pageDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:image" content="{{ $socialImage }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ trim($__env->yieldContent('og_title')) ?: $fullTitle }}">
    <meta name="twitter:description" content="{{ trim($__env->yieldContent('og_description')) ?: $pageDescription }}">
    <meta name="twitter:image" content="{{ $socialImage }}">
    <link rel="icon" href="{{ asset('assets/journal-mark.svg') }}" type="image/svg+xml">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <style>
            html{font-family:Arial,sans-serif;color:#18211f;background:#f8f6f0}body{margin:0}a{color:inherit}.site-shell{min-height:100vh}.container{width:min(1180px,calc(100% - 2rem));margin-inline:auto}.fallback-note{padding:1rem;text-align:center}
        </style>
    @endif
    <script type="application/ld+json">{!! json_encode($structuredData ?? $defaultSchema, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}</script>
    @stack('head')
</head>
<body class="site-shell antialiased">
    <a class="skip-link" href="#main-content">Skip to content</a>
    <div class="reading-progress" data-reading-progress aria-hidden="true"></div>

    <header class="site-header" data-site-header>
        <div class="utility-bar">
            <div class="container utility-inner">
                <p>{{ data_get($site ?? [], 'tagline', 'Independent ideas. Enduring perspective.') }}</p>
                <div class="utility-links">
                    <a href="{{ route('about') }}">Our mission</a>
                    <a href="{{ route('editorial-board') }}">Editorial board</a>
                    @auth
                        @if (auth()->user()->hasAnyRole('admin', 'super-admin', 'editor') && Route::has('admin.dashboard'))
                            <a href="{{ route('admin.dashboard') }}">Dashboard</a>
                        @elseif (auth()->user()->hasRole('reviewer') && Route::has('reviewer.dashboard'))
                            <a href="{{ route('reviewer.dashboard') }}">Dashboard</a>
                        @elseif (auth()->user()->hasRole('author') && Route::has('author.dashboard'))
                            <a href="{{ route('author.dashboard') }}">Dashboard</a>
                        @endif
                    @else
                        @if (Route::has('login'))<a href="{{ route('login') }}">Sign in</a>@endif
                    @endauth
                </div>
            </div>
        </div>
        <div class="container masthead">
            <button class="icon-button mobile-menu-button" type="button" aria-expanded="false" aria-controls="mobile-navigation" data-menu-toggle>
                <span class="sr-only">Open navigation</span>
                <x-public.icon name="menu" />
            </button>
            <a class="brand" href="{{ route('home') }}" aria-label="{{ $siteName }} home">
                <img src="{{ asset('assets/journal-mark.svg') }}" alt="" width="48" height="48">
                <span>
                    <strong>{{ $siteName }}</strong>
                    <small>Journal of ideas &amp; inquiry</small>
                </span>
            </a>
            <button class="search-trigger" type="button" aria-haspopup="dialog" data-search-open>
                <x-public.icon name="search" />
                <span>Search the journal</span>
                <kbd>/</kbd>
            </button>
        </div>
        <nav class="primary-nav" aria-label="Primary navigation">
            <div class="container nav-inner">
                <a href="{{ route('home') }}" @class(['is-active' => request()->routeIs('home')])>Home</a>
                <a href="{{ route('journals.index') }}" @class(['is-active' => request()->routeIs('journals.*')])>Journal</a>
                <a href="{{ route('articles.index') }}" @class(['is-active' => request()->routeIs('articles.*')])>Latest</a>
                <a href="{{ route('categories.index') }}" @class(['is-active' => request()->routeIs('categories.*')])>Disciplines</a>
                <a href="{{ route('authors.index') }}" @class(['is-active' => request()->routeIs('authors.*')])>Contributors</a>
                <a href="{{ route('about') }}" @class(['is-active' => request()->routeIs('about')])>About</a>
                <a href="{{ route('contact') }}" @class(['is-active' => request()->routeIs('contact*')])>Contact</a>
                @if (data_get($site, 'features.author_registration', true) && Route::has('author.register'))
                    <a class="nav-cta" href="{{ route('author.register') }}">Submit your work <x-public.icon name="arrow-up-right" /></a>
                @endif
            </div>
        </nav>
        <nav class="mobile-nav" id="mobile-navigation" aria-label="Mobile navigation" hidden data-mobile-menu>
            <a href="{{ route('journals.index') }}">Journal</a>
            <a href="{{ route('articles.index') }}">Latest publications</a>
            <a href="{{ route('categories.index') }}">Disciplines</a>
            <a href="{{ route('authors.index') }}">Contributors</a>
            <a href="{{ route('about') }}">About the journal</a>
            <a href="{{ route('editorial-board') }}">Editorial board</a>
            <a href="{{ route('contact') }}">Contact</a>
            @if (data_get($site, 'features.author_registration', true) && Route::has('author.register'))<a class="button button-primary" href="{{ route('author.register') }}">Submit your work</a>@endif
        </nav>
    </header>

    @if (session('success') || session('error'))
        <div class="toast-region" role="status" aria-live="polite" data-toast>
            <div @class(['toast', 'toast-error' => session('error')])>
                <x-public.icon :name="session('error') ? 'alert' : 'check'" />
                <span>{{ session('success') ?? session('error') }}</span>
                <button type="button" aria-label="Dismiss notification" data-toast-close><x-public.icon name="close" /></button>
            </div>
        </div>
    @endif

    <main id="main-content" tabindex="-1">
        @yield('content')
    </main>

    <footer class="site-footer">
        @if(data_get($site, 'features.newsletter', true))
            <div class="container footer-lead">
                <div>
                    <p class="eyebrow light">Ideas worth returning to</p>
                    <h2>Read slowly. Think deeply.<br>Stay intellectually restless.</h2>
                </div>
                <x-public.newsletter source="footer" />
            </div>
        @endif
        <div class="container footer-grid">
            <div class="footer-brand">
                <a class="brand brand-inverse" href="{{ route('home') }}">
                    <img src="{{ asset('assets/journal-mark-light.svg') }}" alt="" width="44" height="44">
                    <strong>{{ $siteName }}</strong>
                </a>
                <p>{{ data_get($site ?? [], 'description') }}</p>
            </div>
            <div>
                <h3>Explore</h3>
                <a href="{{ route('articles.index') }}">All articles</a>
                <a href="{{ route('categories.index') }}">Disciplines</a>
                <a href="{{ route('authors.index') }}">Contributors</a>
                <a href="{{ route('search') }}">Advanced search</a>
            </div>
            <div>
                <h3>Journal</h3>
                <a href="{{ route('about') }}">Our mission</a>
                <a href="{{ route('editorial-board') }}">Editorial board</a>
                <a href="{{ route('contact') }}">Contact</a>
                @if (data_get($site, 'features.author_registration', true) && Route::has('author.register'))<a href="{{ route('author.register') }}">Author submissions</a>@endif
            </div>
            <div>
                <h3>Contact &amp; follow</h3>
                @if (data_get($site ?? [], 'contact_email'))
                    <a href="mailto:{{ data_get($site, 'contact_email') }}">{{ data_get($site, 'contact_email') }}</a>
                @endif
                @if (data_get($site ?? [], 'address'))<p>{{ data_get($site, 'address') }}</p>@endif
                @foreach (collect(data_get($site ?? [], 'social', []))->filter() as $network => $url)
                    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer">{{ str($network)->headline() }}</a>
                @endforeach
            </div>
        </div>
        <div class="container footer-bottom">
            <p>&copy; {{ now()->year }} {{ $siteName }}. All rights reserved.</p>
            <div>
                <a href="https://www.octaleads.com" target="_blank" rel="noopener noreferrer">Developed by octaleads (www.octaleads.com)</a>
                <a href="{{ route('sitemap') }}">Sitemap</a>
                <a href="{{ route('robots') }}">Robots</a>
            </div>
        </div>
    </footer>

    <dialog class="search-dialog" data-search-dialog aria-labelledby="search-dialog-title">
        <div class="search-dialog-inner">
            <div class="search-dialog-top">
                <div>
                    <p class="eyebrow">Journal archive</p>
                    <h2 id="search-dialog-title">What are you looking for?</h2>
                </div>
                <button class="icon-button" type="button" aria-label="Close search" data-search-close><x-public.icon name="close" /></button>
            </div>
            <form action="{{ route('search') }}" method="get" role="search" class="overlay-search-form">
                <label class="sr-only" for="overlay-search-input">Search articles, authors, and topics</label>
                <x-public.icon name="search" />
                <input id="overlay-search-input" name="q" type="search" placeholder="Search articles, authors, ideas…" autocomplete="off" data-search-input>
                <button class="button button-primary" type="submit">Search</button>
            </form>
            <div class="search-suggestions">
                <span>Explore:</span>
                <a href="{{ route('articles.index', ['sort' => 'popular']) }}">Most read</a>
                <a href="{{ route('categories.index') }}">Disciplines</a>
                <a href="{{ route('authors.index') }}">Contributors</a>
            </div>
        </div>
    </dialog>
    @stack('scripts')
</body>
</html>
