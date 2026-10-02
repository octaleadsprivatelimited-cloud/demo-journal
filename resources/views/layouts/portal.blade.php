<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow"><title>@yield('title', 'Workspace') · {{ config('app.name') }}</title>
    @if(file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot'))) @vite(['resources/css/app.css', 'resources/js/app.js']) @endif
    <style>{!! file_get_contents(resource_path('css/portal.css')) !!}</style>
    @stack('head')
</head>
<body class="portal-body @if(auth()->check() && auth()->user()->hasAnyRole('admin','super-admin','editor')) admin-simple @endif">
<a href="#portal-main" class="portal-skip">Skip to content</a>
@auth
<div class="portal-shell" data-portal-shell>
    <aside class="portal-sidebar" id="portal-sidebar" data-portal-sidebar>
        <div class="portal-brand"><a href="{{ route('home') }}"><img class="portal-publisher-logo" src="{{ asset('assets/larix-logo-transparent.png') }}" alt="Larix International" width="110" height="44"><span><small>Editorial workspace</small></span></a><button type="button" data-sidebar-close aria-label="Close navigation"><x-portal.icon name="close" /></button></div>
        <nav aria-label="Workspace navigation">
            @if(auth()->user()->hasAnyRole('admin','super-admin','editor'))
                @include('admin.navigation')
            @elseif(auth()->user()->hasRole('reviewer'))
                <p class="portal-nav-label">Peer review</p>
                @foreach(['' => 'All assignments', 'assigned' => 'Invitations', 'in_progress' => 'In progress', 'completed' => 'Completed reports', 'overdue' => 'Overdue', 'declined' => 'Declined', 'cancelled' => 'Cancelled'] as $status => $label)
                    <x-portal.nav-link :href="route('reviewer.dashboard', $status ? ['status' => $status] : [])" icon="review" :active="request()->routeIs('reviewer.dashboard') && request('status', '') === $status">{{ $label }}</x-portal.nav-link>
                @endforeach
                <p class="portal-nav-label">My account</p>
                <x-portal.nav-link :href="route('reviewer.profile.edit')" icon="users" :active="request()->routeIs('reviewer.profile.*')">Profile</x-portal.nav-link>
                <x-portal.nav-link :href="route('reviewer.settings.edit')" icon="settings" :active="request()->routeIs('reviewer.settings.*')">Settings</x-portal.nav-link>
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
        @if(!auth()->user()->hasAnyRole('reviewer','editor','admin','super-admin'))<a class="portal-nav-link" href="{{ route('workflow.index') }}"><x-portal.icon name="file" />Manuscript workflow</a>@endif<div class="portal-sidebar-foot"><a href="{{ route('home') }}"><x-portal.icon name="arrow" :size="17" />View journal</a><form method="post" action="{{ route('logout') }}">@csrf<button><x-portal.icon name="logout" :size="17" />Sign out</button></form></div>
    </aside>
    <div class="portal-overlay" data-sidebar-close hidden></div>
    <div class="portal-page">
        <header class="portal-topbar"><button class="portal-menu" type="button" data-sidebar-open aria-controls="portal-sidebar" aria-expanded="false"><x-portal.icon name="menu" /><span class="sr-only">Open navigation</span></button><div class="portal-topbar-title"><span>@yield('section', 'Workspace')</span><small>{{ now()->format('l, j F') }}</small></div><div class="portal-account"><button class="portal-user" type="button" data-account-toggle aria-expanded="false" aria-controls="portal-account-menu"><span>@if(auth()->user()->profile_image_path)<img class="portal-account-avatar" src="{{ Illuminate\Support\Facades\Storage::disk('public')->url(auth()->user()->profile_image_path) }}" alt="" width="38" height="38">@else{{ str(auth()->user()->name)->substr(0,1)->upper() }}@endif</span><div><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->roles->first()?->name ?? 'Member' }}</small></div><span class="sr-only">Open account menu</span></button><div class="portal-account-menu" id="portal-account-menu" data-account-menu hidden><div class="portal-account-details"><strong>{{ auth()->user()->name }}</strong><span>{{ auth()->user()->email }}</span><small>{{ str(auth()->user()->roles->first()?->name ?? 'Member')->headline() }}</small></div>@if(auth()->user()->hasRole('editor'))<a href="{{ route('editor.profile.edit') }}">My profile</a>@elseif(auth()->user()->hasAnyRole('author','contributor'))<a href="{{ route('author.profile.edit') }}">My profile</a>@endif<a href="{{ route('home') }}"><x-portal.icon name="arrow" :size="16" />View journal</a><form method="post" action="{{ route('logout') }}">@csrf<button type="submit"><x-portal.icon name="logout" :size="16" />Sign out</button></form></div></div></header>
        <main id="portal-main" class="portal-main" tabindex="-1"><div class="portal-heading"><div><p class="portal-eyebrow">@yield('eyebrow', 'Editorial workspace')</p><h1>@yield('page-title', 'Overview')</h1><p>@yield('page-description')</p></div><div class="portal-actions">@yield('page-actions')</div></div><x-portal.flash />@yield('content')</main>
    </div>
</div>
@else
<main class="auth-shell @if(request()->routeIs('login')) auth-login @endif"><a class="auth-brand" href="{{ route('home') }}"><img class="portal-publisher-logo" src="{{ asset('assets/larix-logo-transparent.png') }}" alt="Larix International" width="110" height="44"><span><small>Singapore Journal of Cardiology</small></span></a><section class="auth-card"><div class="auth-card-head"><p class="portal-eyebrow">@yield('auth-eyebrow', 'Secure journal access')</p><h1>@yield('page-title', 'Welcome')</h1><p>@yield('page-description')</p></div><x-portal.flash />@yield('content')</section><p class="auth-foot"><a href="{{ route('home') }}">← Return to the journal</a></p></main>
@endauth
<script>{!! file_get_contents(resource_path('js/portal.js')) !!}</script>@stack('scripts')
@include('components.error-popup')
<script>{!! file_get_contents(resource_path('js/upload-guards.js')) !!}</script>
</body></html>
