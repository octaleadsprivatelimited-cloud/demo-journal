@extends('layouts.portal')
@section('title', 'People & roles')
@section('section', 'Super Admin control')
@section('eyebrow', 'Access governance')
@section('page-title', 'People & role applications')
@section('page-description', 'Approve account applications, manage access, and maintain role assignments. These controls are restricted to Super Admins.')
@section('page-actions')
    <a class="portal-button" href="{{ route('admin.roles.index') }}">Manage roles</a>
    <a class="portal-button primary" href="{{ route('admin.users.create') }}"><x-portal.icon name="plus" :size="17" />New user</a>
@endsection

@section('content')
    <div class="portal-card">
        <div class="portal-card-head">
            <div><h2>Pending applications</h2><p>Applicants receive no role or workspace access until you approve them.</p></div>
            <x-portal.status :value="$applications->isEmpty() ? 'clear' : 'pending'" />
        </div>
        @if($applications->isEmpty())
            <x-portal.empty title="No applications awaiting approval" message="New Author, Editor, and Admin applications will appear here." />
        @else
            <div class="portal-table-wrap"><table class="portal-table">
                <thead><tr><th>Applicant</th><th>Requested access</th><th>Professional details</th><th>Submitted</th><th></th></tr></thead>
                <tbody>
                @foreach($applications as $application)
                    <tr>
                        <td><span class="row-title">{{ $application->name }}</span><small>{{ $application->email }}</small></td>
                        <td><x-portal.status :value="$application->requested_role" /></td>
                        <td>{{ $application->designation ?: '—' }}<small>{{ $application->organization }}</small></td>
                        <td>{{ $application->created_at->diffForHumans() }}</td>
                        <td><div class="row-actions">
                            <form method="post" action="{{ route('admin.users.approve', $application) }}" data-confirm="Approve this {{ $application->requested_role }} application and send an email verification link?">@csrf<button class="portal-button small primary" type="submit">Approve</button></form>
                            <form method="post" action="{{ route('admin.users.reject', $application) }}" data-confirm="Reject this application? The account will remain unable to sign in.">@csrf<button class="portal-button small danger" type="submit">Reject</button></form>
                        </div></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
    </div>

    <div class="portal-card" style="margin-top:1.25rem">
        <form class="filter-bar" method="get">
            <div class="portal-field search-field"><label for="q">Search people</label><input class="portal-input" id="q" name="q" value="{{ request('q') }}" placeholder="Name or email"></div>
            <div class="portal-field"><label for="role">Role</label><select class="portal-select" id="role" name="role"><option value="">All roles</option>@foreach($roles as $role)<option value="{{ $role->slug }}" @selected(request('role') === $role->slug)>{{ $role->name }}</option>@endforeach</select></div>
            <button class="portal-button" type="submit">Filter</button>
        </form>
        @if($users->isEmpty())
            <x-portal.empty title="No people found" message="Adjust the filter or create an account." action="New user" :href="route('admin.users.create')" />
        @else
            <div class="portal-table-wrap"><table class="portal-table">
                <thead><tr><th>Person</th><th>Roles</th><th>Author profile</th><th>Verification</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @foreach($users as $user)
                    <tr>
                        <td><a class="row-title" href="{{ route('admin.users.edit', $user) }}">{{ $user->name }}</a><small>{{ $user->email }}{{ $user->organization ? ' · '.$user->organization : '' }}</small></td>
                        <td>{{ $user->roles->pluck('name')->join(', ') ?: 'No role' }}</td>
                        <td>{{ $user->author ? ($user->author->is_verified ? 'Verified' : 'Unverified') : '—' }}</td>
                        <td>{{ $user->hasVerifiedEmail() ? 'Email verified' : 'Pending email' }}</td>
                        <td><x-portal.status :value="$user->isActive() ? 'active' : ($user->status === 'active' ? 'suspended' : $user->status)" /></td>
                        <td><div class="row-actions"><a class="portal-button small" href="{{ route('admin.users.edit', $user) }}">Edit</a>@unless(auth()->id() === $user->id)<form method="post" action="{{ route('admin.users.destroy', $user) }}" data-confirm="Suspend this account?">@csrf @method('delete')<button class="portal-button small danger" type="submit">Suspend</button></form>@endunless</div></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="pagination-wrap">{{ $users->links() }}</div>
        @endif
    </div>
@endsection
