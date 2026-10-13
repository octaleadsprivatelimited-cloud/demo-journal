@extends('layouts.portal')
@section('title', 'Dashboard')
@section('section', 'Dashboard')
@section('page-title', 'Journal dashboard')
@section('page-description', 'Manage manuscripts, people and your journal website.')
@section('page-actions')
    <a class="portal-button" href="{{ route('home') }}">View website ↗</a>
    <a class="portal-button primary" href="{{ route('admin.articles.create') }}">Add article</a>
@endsection
@section('content')
<div class="metric-grid">
    <a href="{{ route('admin.articles.index', ['status' => 'published']) }}"><x-portal.metric label="Published articles" :value="$stats['published']" hint="Visible on the website" /></a>
    <a href="{{ route('admin.articles.index', ['status' => 'draft']) }}"><x-portal.metric label="Draft articles" :value="$stats['drafts']" hint="Continue editing" /></a>
    <a href="{{ route('admin.submissions.index', ['status' => 'pending']) }}"><x-portal.metric label="Pending submissions" :value="$stats['pending']" hint="Awaiting a decision" tone="gold" /></a>
    @if(auth()->user()->hasRole('super-admin'))
        <a href="{{ route('admin.users.index', ['tab' => 'applications']) }}"><x-portal.metric label="Account approvals" :value="$stats['applications']" hint="Review registration requests" tone="blue" /></a>
    @else
        <a href="{{ route('admin.reviews.index') }}"><x-portal.metric label="Reviews due soon" :value="$stats['reviewsDue']" hint="Within seven days" tone="blue" /></a>
    @endif
</div>
<section class="portal-card admin-quick-actions">
    <div class="portal-card-head"><h2>Quick actions</h2></div>
    <div class="portal-card-body admin-start-grid">
        <a class="admin-start-card" href="{{ route('workflow.index') }}"><strong>Review manuscripts →</strong><span>Check submissions, assign staff and record decisions.</span></a>
        @if(auth()->user()->hasRole('super-admin'))
            <a class="admin-start-card" href="{{ route('admin.users.create', ['role' => 'editor']) }}"><strong>Add editor →</strong><span>Create an approved login and send email verification.</span></a>
            <a class="admin-start-card" href="{{ route('admin.users.create', ['role' => 'reviewer']) }}"><strong>Add reviewer →</strong><span>Create an approved login for peer review.</span></a>
            <a class="admin-start-card" href="{{ route('admin.pages.index') }}"><strong>Edit website →</strong><span>Update journal pages and policies.</span></a>
        @else
            <a class="admin-start-card" href="{{ route('admin.assign-reviewer.index') }}"><strong>Assign reviewers →</strong><span>Choose reviewers for submitted manuscripts.</span></a>
            <a class="admin-start-card" href="{{ route('admin.articles.index') }}"><strong>Manage articles →</strong><span>Edit manuscripts and published articles.</span></a>
            <a class="admin-start-card" href="{{ route('admin.contacts.index') }}"><strong>Read enquiries →</strong><span>{{ $stats['contacts'] }} new reader messages.</span></a>
        @endif
    </div>
</section>
<div class="dashboard-grid">
    <section class="portal-card">
        <div class="portal-card-head"><h2>Submissions awaiting attention</h2><a class="portal-button small" href="{{ route('workflow.index') }}">All manuscripts</a></div>
        @forelse($pendingSubmissions as $submission)
            <a class="admin-queue-row" href="{{ route('admin.submissions.show', $submission) }}"><span class="admin-queue-main"><strong>{{ $submission->article?->title ?? 'Archived manuscript' }}</strong><small>{{ $submission->submitter?->name ?? 'System' }} · {{ $submission->submitted_at?->diffForHumans() }}</small></span><x-portal.status value="pending" /></a>
        @empty
            <div class="portal-card-body"><p class="admin-help-text">No submissions awaiting assessment.</p></div>
        @endforelse
    </section>
    <section class="portal-card">
        <div class="portal-card-head"><h2>Reviews due soon</h2><a class="portal-button small" href="{{ route('admin.reviews.index') }}">All reviews</a></div>
        @forelse($reviewsNeedingAttention as $review)
            <a class="admin-queue-row" href="{{ route('admin.reviews.show', $review) }}"><span class="admin-queue-main"><strong>{{ $review->article?->title ?? 'Archived manuscript' }}</strong><small>{{ $review->reviewer?->name ?? 'Unassigned' }} · Due {{ $review->due_at?->toFormattedDateString() }}</small></span><x-portal.status :value="$review->status" /></a>
        @empty
            <div class="portal-card-body"><p class="admin-help-text">No reviews due within seven days.</p></div>
        @endforelse
    </section>
</div>
<details class="admin-help portal-card">
    <summary>How publishing works — 6 steps</summary>
    <ol class="admin-publishing-steps">
        @foreach(array_slice(config('workflow-guide.phases'), 0, 6) as $phase)
            <li><strong>{{ $phase['label'] }}</strong><p>{{ $phase['owner'] }}</p><p>{{ $phase['description'] }}</p></li>
        @endforeach
    </ol>
    <p class="admin-help-text">Open a manuscript and read What happens next. The available actions follow its current stage.</p>
</details>
@endsection
