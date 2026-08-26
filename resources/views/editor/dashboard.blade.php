@extends('layouts.portal')
@section('title', 'Editor dashboard')
@section('section', 'Editor workspace')
@section('eyebrow', 'Editorial queue')
@section('page-title', 'Editor dashboard')
@section('page-description', 'Review submissions, shape manuscripts, and prepare approved work for publication.')
@section('page-actions')
    <a class="portal-button" href="{{ route('admin.articles.index') }}">All articles</a>
    <a class="portal-button primary" href="{{ route('admin.submissions.index') }}">Open review queue</a>
@endsection

@section('content')
    <div class="metric-grid">
        <x-portal.metric label="Pending submissions" :value="$stats['pending']" tone="gold" hint="Awaiting editorial review" />
        <x-portal.metric label="Under review" :value="$stats['underReview']" tone="blue" hint="Currently being assessed" />
        <x-portal.metric label="Approved" :value="$stats['approved']" hint="Ready for publication" />
        <x-portal.metric label="Scheduled" :value="$stats['scheduled']" hint="Queued for release" />
    </div>

    <div class="portal-card" style="margin-top:1.25rem">
        <div class="portal-card-head"><div><h2>Submissions awaiting attention</h2><p>The newest items in the editorial queue.</p></div></div>
        @if($submissions->isEmpty())
            <x-portal.empty title="The queue is clear" message="New submissions will appear here." />
        @else
            <div class="portal-table-wrap"><table class="portal-table"><thead><tr><th>Article</th><th>Author</th><th>Submitted</th><th></th></tr></thead><tbody>
            @foreach($submissions as $submission)
                <tr><td><span class="row-title">{{ $submission->article->title }}</span><small>{{ $submission->article->trashed() ? 'Archived manuscript' : '' }}</small></td><td>{{ $submission->submitter?->name ?? 'System' }}</td><td>{{ $submission->submitted_at?->diffForHumans() }}</td><td><a class="portal-button small" href="{{ route('admin.submissions.show', $submission) }}">Review</a></td></tr>
            @endforeach
            </tbody></table></div>
        @endif
    </div>
@endsection
