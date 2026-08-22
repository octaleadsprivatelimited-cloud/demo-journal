<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow"><title>@yield('title', 'Workspace') — {{ config('app.name', 'octaleads Journal') }}</title>
    @if(file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot'))) @vite(['resources/css/app.css', 'resources/js/app.js']) @endif
    <style>{!! file_get_contents(resource_path('css/portal.css')) !!}</style>
    @stack('head')
</head>
<body class="portal-body">
<a href="#portal-main" class="portal-skip">Skip to content</a>
@auth
<div class="portal-shell" data-portal-shell>
    <aside class="portal-sidebar" id="portal-sidebar" data-portal-sidebar>
        <div class="portal-brand"><a href="{{ route('home') }}"><span class="portal-brand-mark">O</span><span><strong>{{ config('app.name', 'octaleads Journal') }}</strong><small>Editorial workspace</small></span></a><button type="button" data-sidebar-close aria-label="Close navigation"><x-portal.icon name="close" /></button></div>
        <nav aria-label="Workspace navigation">
            @if(auth()->user()->hasAnyRole('admin','super-admin','editor'))
                <p class="portal-nav-label">Editorial</p>
                <x-portal.nav-link :href="route('admin.dashboard')" icon="dashboard" :active="request()->routeIs('admin.dashboard')">Overview</x-portal.nav-link>
                <x-portal.nav-link :href="route('admin.articles.index')" icon="article" :active="request()->routeIs('admin.articles.*')">Articles</x-portal.nav-link>
                <x-portal.nav-link :href="route('admin.submissions.index')" icon="review" :active="request()->routeIs('admin.submissions.*')">Submissions</x-portal.nav-link>
                <x-portal.nav-link :href="route('admin.reviews.index')" icon="review" :active="request()->routeIs('admin.reviews.*')">Reviews</x-portal.nav-link>
                <p class="portal-nav-label">Publishing</p>
                <x-portal.nav-link :href="route('admin.categories.index')" icon="folder" :active="request()->routeIs('admin.categories.*')">Categories</x-portal.nav-link>
                <x-portal.nav-link :href="route('admin.tags.index')" icon="tag" :active="request()->routeIs('admin.tags.*')">Tags</x-portal.nav-link>
                <x-portal.nav-link :href="route('admin.media.index')" icon="media" :active="request()->routeIs('admin.media.*')">Media library</x-portal.nav-link>
                @if(auth()->user()->hasPermission('comments.moderate'))
                    <x-portal.nav-link :href="route('admin.comments.index')" icon="review" :active="request()->routeIs('admin.comments.*')">Comments</x-portal.nav-link>
                @endif
                @if(auth()->user()->hasAnyRole('admin','super-admin'))
                    <p class="portal-nav-label">Audience &amp; system</p>
                    <x-portal.nav-link :href="route('admin.users.index')" icon="users" :active="request()->routeIs('admin.users.*')">People &amp; roles</x-portal.nav-link>
                    <x-portal.nav-link :href="route('admin.contacts.index')" icon="mail" :active="request()->routeIs('admin.contacts.*')">Enquiries</x-portal.nav-link>
                    <x-portal.nav-link :href="route('admin.newsletter.index')" icon="mail" :active="request()->routeIs('admin.newsletter.*')">Newsletter</x-portal.nav-link>
                    <x-portal.nav-link :href="route('admin.settings.index')" icon="settings" :active="request()->routeIs('admin.settings.*')">Settings</x-portal.nav-link>
                    <x-portal.nav-link :href="route('admin.audit.index')" icon="audit" :active="request()->routeIs('admin.audit.*')">Audit log</x-portal.nav-link>
                @endif
            @elseif(auth()->user()->hasRole('reviewer'))
                <p class="portal-nav-label">Peer review</p><x-portal.nav-link :href="route('reviewer.dashboard')" icon="dashboard" :active="request()->routeIs('reviewer.dashboard')">Assignments</x-portal.nav-link>
            @else
                <p class="portal-nav-label">Author studio</p><x-portal.nav-link :href="route('author.dashboard')" icon="dashboard" :active="request()->routeIs('author.dashboard')">Overview</x-portal.nav-link>
                <x-portal.nav-link :href="route('author.articles.index')" icon="article" :active="request()->routeIs('author.articles.*')">My manuscripts</x-portal.nav-link>
                <x-portal.nav-link :href="route('author.submissions.index')" icon="review" :active="request()->routeIs('author.submissions.*')">Submissions</x-portal.nav-link>
                <x-portal.nav-link :href="route('author.reviews.index')" icon="review" :active="request()->routeIs('author.reviews.*')">Reviewer feedback</x-portal.nav-link>
                <x-portal.nav-link :href="route('author.profile.edit')" icon="users" :active="request()->routeIs('author.profile.*')">Profile</x-portal.nav-link>
                <x-portal.nav-link :href="route('author.settings.edit')" icon="settings" :active="request()->routeIs('author.settings.*')">Settings</x-portal.nav-link>
                <a class="portal-button sidebar-action" href="{{ route('author.articles.create') }}"><x-portal.icon name="plus" :size="17" />New manuscript</a>
            @endif
        </nav>
        <div class="portal-sidebar-foot"><a href="{{ route('home') }}"><x-portal.icon name="arrow" :size="17" />View journal</a><form method="post" action="{{ route('logout') }}">@csrf<button><x-portal.icon name="logout" :size="17" />Sign out</button></form></div>
    </aside>
    <div class="portal-overlay" data-sidebar-close hidden></div>
    <div class="portal-page">
        <header class="portal-topbar"><button class="portal-menu" type="button" data-sidebar-open aria-controls="portal-sidebar" aria-expanded="false"><x-portal.icon name="menu" /><span class="sr-only">Open navigation</span></button><div class="portal-topbar-title"><span>@yield('section', 'Workspace')</span><small>{{ now()->format('l, j F') }}</small></div><div class="portal-user"><span>{{ str(auth()->user()->name)->substr(0,1)->upper() }}</span><div><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->roles()->value('name') ?? 'Member' }}</small></div></div></header>
        <main id="portal-main" class="portal-main" tabindex="-1"><div class="portal-heading"><div><p class="portal-eyebrow">@yield('eyebrow', 'Editorial workspace')</p><h1>@yield('page-title', 'Overview')</h1><p>@yield('page-description')</p></div><div class="portal-actions">@yield('page-actions')</div></div><x-portal.flash />@yield('content')</main>
    </div>
</div>
@else
<main class="auth-shell"><a class="auth-brand" href="{{ route('home') }}"><span class="portal-brand-mark">O</span><span><strong>{{ config('app.name', 'octaleads Journal') }}</strong><small>Journal of ideas &amp; inquiry</small></span></a><section class="auth-card"><div class="auth-card-head"><p class="portal-eyebrow">Secure author access</p><h1>@yield('page-title', 'Welcome')</h1><p>@yield('page-description')</p></div><x-portal.flash />@yield('content')</section><p class="auth-foot"><a href="{{ route('home') }}">← Return to the journal</a></p></main>
@endauth
<script>{!! file_get_contents(resource_path('js/portal.js')) !!}</script>@stack('scripts')
</body></html>
