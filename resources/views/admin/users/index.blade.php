@extends('layouts.portal')
@section('title', 'People')
@section('section', 'People')
@section('page-title', 'People')
@section('page-description', 'Add editors and reviewers directly, or approve people who registered themselves. Super Admin approval and a verified email are required for access.')
@section('page-actions')
    <a class="portal-button primary" href="{{ route('admin.users.create', ['role' => 'editor']) }}">Add editor</a>
    <a class="portal-button" href="{{ route('admin.users.create', ['role' => 'reviewer']) }}">Add reviewer</a>
@endsection

@section('content')
    <nav class="admin-people-tabs" aria-label="People sections">
        <a href="{{ route('admin.users.index') }}" @class(['portal-button', 'primary' => $tab === 'accounts']) @if($tab === 'accounts') aria-current="page" @endif>Accounts</a>
        <a href="{{ route('admin.users.index', ['tab' => 'applications']) }}" @class(['portal-button', 'primary' => $tab === 'applications']) @if($tab === 'applications') aria-current="page" @endif>Account approvals <span class="admin-count">{{ $applications->count() }}</span></a>
    </nav>
    @if($tab === 'applications')
    <div class="portal-card">
        <div class="portal-card-head">
            <div><h2>Account approvals</h2><p>Approve the requested role. The applicant must also verify their email before signing in.</p></div>
            <x-portal.status :value="$applications->isEmpty() ? 'clear' : 'pending'" />
        </div>
        @if($applications->isEmpty())
            <x-portal.empty title="No applications awaiting approval" message="New registration applications will appear here. You can also add an editor or reviewer using the buttons above." />
        @else
            <div class="portal-table-wrap"><table class="portal-table">
                <thead><tr><th>Applicant</th><th>Role</th><th>Email verification</th><th>Submitted</th><th>Decision</th></tr></thead>
                <tbody>
                @foreach($applications as $application)
                    <tr>
                        <td><span class="row-title">{{ $application->name }}</span><small>{{ $application->email }}</small>@if($application->designation || $application->organization)<small>{{ $application->designation }} · {{ $application->organization }}</small>@endif</td>
                        <td><x-portal.status :value="$application->requested_role" /></td>
                        <td>{{ $application->hasVerifiedEmail() ? 'Verified' : 'Awaiting verification' }}</td>
                        <td>{{ $application->created_at->diffForHumans() }}</td>
                        <td><div class="row-actions">
                            <form method="post" action="{{ route('admin.users.approve', $application) }}" data-confirm="Approve this {{ $application->requested_role }} application? Portal access also requires a verified email.">@csrf<button class="portal-button small primary" type="submit">Approve</button></form>
                            <form method="post" action="{{ route('admin.users.reject', $application) }}" data-confirm="Reject this application? The account will remain unable to sign in.">@csrf<button class="portal-button small danger" type="submit">Reject</button></form>
                        </div></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
    </div>
    @else
    <div class="portal-card">
        <form class="filter-bar" method="get">
            <div class="portal-field search-field"><label for="q">Search people</label><input class="portal-input" id="q" name="q" value="{{ request('q') }}" placeholder="Name or email"></div>
            <div class="portal-field"><label for="role">Role</label><select class="portal-select" id="role" name="role"><option value="">All roles</option>@foreach($roles as $role)<option value="{{ $role->slug }}" @selected(request('role') === $role->slug)>{{ $role->name }}</option>@endforeach</select></div>
            <button class="portal-button" type="submit">Filter</button>
        </form>
        @if($users->isEmpty())
            <x-portal.empty title="No people found" message="Adjust the filter, add an editor or reviewer, or open Account approvals for pending registrations." />
        @else
            <div class="portal-table-wrap"><table class="portal-table">
                <thead><tr><th>Person</th><th>Role</th><th>Email verification</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                @foreach($users as $user)
                    <tr>
                        <td><a class="row-title" href="{{ route('admin.users.edit', $user) }}">{{ $user->name }}</a><small>{{ $user->email }}{{ $user->organization ? ' · '.$user->organization : '' }}</small></td>
                        <td>{{ $user->roles->pluck('name')->join(', ') ?: 'No role' }}</td>
                        <td>{{ $user->hasVerifiedEmail() ? 'Email verified' : 'Pending email' }}</td>
                        <td><x-portal.status :value="$user->isActive() ? 'active' : ($user->status === 'active' ? 'suspended' : $user->status)" /></td>
                        <td><div class="row-actions"><a class="portal-button small" href="{{ route('admin.users.edit', $user) }}">Edit</a>@unless(auth()->id() === $user->id)<details class="admin-row-more"><summary>More</summary><form method="post" action="{{ route('admin.users.destroy', $user) }}" data-confirm="Suspend this account?">@csrf @method('delete')<button class="portal-button small danger" type="submit">Suspend</button></form></details>@endunless</div></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="pagination-wrap">{{ $users->links() }}</div>
        @endif
    </div>
    @endif
    <details class="admin-help">
        <summary>More account tools</summary>
        <div class="admin-secondary-links">
            <a class="portal-button" href="{{ route('admin.users.create') }}">Add another role</a>
            <a class="portal-button" href="{{ route('admin.roles.index') }}">Roles & permissions</a>
            <a class="portal-button" href="{{ route('workflow.staff') }}">Staff profiles & availability</a>
            <a class="portal-button" href="{{ route('admin.readiness.index', 'editorial-members') }}">Public editorial board</a>
        </div>
    </details>
@endsection
