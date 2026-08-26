@extends('layouts.portal')

@section('title', 'Submissions')
@section('section', 'Author studio')
@section('eyebrow', 'Editorial workflow')
@section('page-title', 'Submission history')
@section('page-description', 'Every submission round, frozen manuscript version, review progress, and decision in one place.')

@section('content')
    <div class="portal-card">
        @if ($submissions->isEmpty())
            <x-portal.empty title="No submissions yet" message="When a draft is ready, submit it from the manuscript record." action="View manuscripts" :href="route('author.articles.index')" />
        @else
            <div class="portal-table-wrap">
                <table class="portal-table">
                    <thead>
                        <tr><th>Manuscript</th><th>Round / version</th><th>Status</th><th>Reviews</th><th>Submitted</th><th>Decision</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($submissions as $submission)
                            <tr>
                                <td>
                                    @if ($submission->article->trashed())
                                        <span class="row-title">{{ $submission->article->title }}</span>
                                        <small>Archived manuscript</small>
                                    @else
                                        <a class="row-title" href="{{ route('author.articles.show', $submission->article) }}">{{ $submission->article->title }}</a>
                                    @endif
                                </td>
                                <td>Round {{ $submission->round }}<small>Version {{ $submission->version?->version_number ?? '—' }}</small></td>
                                <td><x-portal.status :value="$submission->status" /></td>
                                <td>{{ $submission->reviews->whereNotNull('completed_at')->count() }} / {{ $submission->reviews->count() }} completed</td>
                                <td>{{ $submission->submitted_at->format('d M Y') }}</td>
                                <td>{{ $submission->decision_at?->format('d M Y') ?? 'Pending' }}</td>
                                <td>
                                    @unless ($submission->article->trashed())
                                        <a class="portal-button small" href="{{ route('author.articles.show', $submission->article) }}">Open record</a>
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pagination-wrap">{{ $submissions->links() }}</div>
        @endif
    </div>
@endsection
