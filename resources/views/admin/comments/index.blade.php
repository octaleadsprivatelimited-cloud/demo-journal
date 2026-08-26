@extends('layouts.portal')

@section('title', 'Comment moderation')
@section('section', 'Publishing')
@section('eyebrow', 'Reader discussion')
@section('page-title', 'Comment moderation')
@section('page-description', 'Approve thoughtful contributions and remove rejected or spam submissions before they appear publicly.')

@section('content')
    <div class="portal-card">
        <form class="filter-bar" method="get">
            <div class="portal-field search-field">
                <label for="q">Search comments</label>
                <input class="portal-input" id="q" name="q" value="{{ request('q') }}" placeholder="Comment, reader, email, or article…">
            </div>
            <div class="portal-field">
                <label for="status">Status</label>
                <select class="portal-select" id="status" name="status">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ str($status->value)->headline() }}</option>
                    @endforeach
                </select>
            </div>
            <button class="portal-button" type="submit">Filter</button>
        </form>

        @if ($comments->isEmpty())
            <x-portal.empty title="No comments found" message="Reader comments awaiting moderation will appear here." />
        @else
            <div class="portal-table-wrap">
                <table class="portal-table">
                    <thead><tr><th>Reader</th><th>Comment</th><th>Article</th><th>Status</th><th>Submitted</th><th>Actions</th></tr></thead>
                    <tbody>
                    @foreach ($comments as $comment)
                        <tr>
                            <td><span class="row-title">{{ $comment->user?->name ?? $comment->guest_name ?? 'Anonymous' }}</span><small>{{ $comment->user?->email ?? $comment->guest_email }}</small></td>
                            <td style="min-width:260px">{{ str($comment->body)->limit(180) }}</td>
                            <td>
                                @if ($comment->article->trashed())
                                    <span class="row-title">{{ str($comment->article->title)->limit(60) }}</span>
                                    <small>Archived article</small>
                                @else
                                    <a class="row-title" href="{{ route('articles.show', $comment->article->slug) }}" target="_blank" rel="noopener">{{ str($comment->article->title)->limit(60) }}</a>
                                @endif
                            </td>
                            <td><x-portal.status :value="$comment->status" /></td>
                            <td>{{ $comment->created_at->format('d M Y, H:i') }}</td>
                            <td>
                                <div class="row-actions">
                                    @foreach ([\App\Enums\CommentStatus::Approved, \App\Enums\CommentStatus::Rejected, \App\Enums\CommentStatus::Spam, \App\Enums\CommentStatus::Pending] as $status)
                                        @if ($comment->status !== $status)
                                            <form method="post" action="{{ route('admin.comments.update', $comment) }}">
                                                @csrf @method('patch')
                                                <input type="hidden" name="status" value="{{ $status->value }}">
                                                <button @class(['portal-button', 'small', 'primary' => $status === \App\Enums\CommentStatus::Approved, 'danger' => in_array($status, [\App\Enums\CommentStatus::Rejected, \App\Enums\CommentStatus::Spam], true)]) type="submit">{{ str($status->value)->headline() }}</button>
                                            </form>
                                        @endif
                                    @endforeach
                                    <form method="post" action="{{ route('admin.comments.destroy', $comment) }}" data-confirm="Archive this comment?">
                                        @csrf @method('delete')
                                        <button class="portal-button small danger" type="submit">Archive</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pagination-wrap">{{ $comments->links() }}</div>
        @endif
    </div>
@endsection
