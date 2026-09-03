<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\CommentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CommentModerationRequest;
use App\Models\Comment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class CommentController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('moderateComments');

        $comments = Comment::query()
            ->with(['article:id,title,slug', 'user:id,name,email'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.addcslashes($request->string('q')->toString(), '%_').'%';

                $query->where(function ($nested) use ($term): void {
                    $nested->where('body', 'like', $term)
                        ->orWhere('guest_name', 'like', $term)
                        ->orWhere('guest_email', 'like', $term)
                        ->orWhereHas('article', fn ($article) => $article->where('title', 'like', $term));
                });
            })
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.comments.index', [
            'comments' => $comments,
            'statuses' => CommentStatus::cases(),
        ]);
    }

    public function update(CommentModerationRequest $request, Comment $comment): RedirectResponse
    {
        $status = $request->enum('status', CommentStatus::class);

        $comment->update([
            'status' => $status,
            'approved_at' => $status === CommentStatus::Approved
                ? ($comment->approved_at ?? now())
                : null,
        ]);

        return back()->with('success', 'Comment moderation status updated.');
    }

    public function destroy(Comment $comment): RedirectResponse
    {
        Gate::authorize('moderateComments');
        $comment->delete();

        return back()->with('success', 'Comment archived.');
    }
}
