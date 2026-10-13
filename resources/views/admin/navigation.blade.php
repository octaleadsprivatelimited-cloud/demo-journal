@php
    $user = auth()->user();
    $super = $user->hasRole('super-admin');
    $website = [
        ['Pages & policies', 'admin.pages.index', 'settings.manage', [], null],
        ['Editorial board', 'admin.readiness.index', 'settings.manage', ['resource' => 'editorial-members'], 'super'],
        ['Categories / disciplines', 'admin.categories.index', 'categories.manage', [], null],
    ];
    $tools = [
        ['Assign reviewers', 'admin.assign-reviewer.index', 'articles.review', [], null],
        ['Review reports', 'admin.reviews.index', 'articles.review', [], null],
        ['Submission records', 'admin.submissions.index', 'articles.review', [], null],
        ['Staff directory', 'workflow.staff', 'articles.review', [], null],
        ['Media library', 'admin.media.index', 'media.manage', [], null],
        ['Article files', 'admin.uploads.index', 'media.manage', [], 'admin'],
        ['Tags', 'admin.tags.index', 'tags.manage', [], null],
        ['Author profiles', 'admin.readiness.index', 'settings.manage', ['resource' => 'authors'], 'super'],
        ['Volumes', 'admin.readiness.index', 'settings.manage', ['resource' => 'volumes'], 'super'],
        ['Issues', 'admin.readiness.index', 'settings.manage', ['resource' => 'issues'], 'super'],
        ['Enquiries', 'admin.contacts.index', 'contacts.manage', [], null],
        ['Comments', 'admin.comments.index', 'comments.moderate', [], null],
        ['Newsletter', 'admin.newsletter.index', 'newsletter.manage', [], null],
        ['General settings', 'admin.settings.index', 'settings.manage', [], 'super'],
        ['Workflow settings', 'workflow.settings', 'settings.manage', [], 'super'],
        ['Email templates', 'admin.email-templates', 'settings.manage', [], 'admin'],
        ['Roles & permissions', 'admin.roles.index', 'users.manage', [], 'super'],
        ['Activity log', 'admin.audit.index', 'audit.view', [], null],
    ];
    $visible = fn ($links) => collect($links)->filter(fn ($link) => $user->hasPermission($link[2])
        && ($link[4] !== 'super' || $super)
        && ($link[4] !== 'admin' || $user->hasAnyRole('admin', 'super-admin')));
    $active = fn ($link) => request()->routeIs(str_replace(['.index', '.create'], '.*', $link[1]))
        && (!isset($link[3]['resource']) || request()->route('resource') === $link[3]['resource']);
@endphp

<x-portal.nav-link :href="$user->hasAnyRole('admin','super-admin') ? route('admin.dashboard') : route('editor.dashboard')" icon="dashboard" :active="request()->routeIs('admin.dashboard','editor.dashboard')">Dashboard</x-portal.nav-link>
@if($user->hasPermission('articles.review'))
    <x-portal.nav-link :href="route('workflow.index')" icon="review" :active="request()->routeIs('workflow.*') && !request()->routeIs('workflow.staff*','workflow.settings*')">Manuscripts</x-portal.nav-link>
@endif
@if($user->hasPermission('articles.view'))
    <x-portal.nav-link :href="route('admin.articles.index')" icon="article" :active="request()->routeIs('admin.articles.*')">Articles</x-portal.nav-link>
@endif
@if($super)
    <x-portal.nav-link :href="route('admin.users.index')" icon="users" :active="request()->routeIs('admin.users.*')">People</x-portal.nav-link>
@endif
@foreach(['Website' => $website, 'More tools' => $tools] as $label => $links)
    @php $items = $visible($links); @endphp
    @if($items->isNotEmpty())
        <details class="admin-nav-section" @if($items->contains($active)) open @endif>
            <summary>{{ $label }}</summary>
            <div>
                @foreach($items as $link)
                    <x-portal.nav-link :href="route($link[1], $link[3])" icon="arrow" :active="$active($link)">{{ $link[0] }}</x-portal.nav-link>
                @endforeach
            </div>
        </details>
    @endif
@endforeach
@if($user->hasAnyRole('admin','super-admin'))
    <x-portal.nav-link :href="route('admin.account.edit')" icon="users" :active="request()->routeIs('admin.account.*')">My account</x-portal.nav-link>
@endif
@if($user->hasRole('editor'))
    <x-portal.nav-link :href="route('editor.profile.edit')" icon="users" :active="request()->routeIs('editor.profile.*')">My profile</x-portal.nav-link>
@endif
