<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Events\ReviewAssigned;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewRequest;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class ReviewController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Review::class);

        return view('admin.reviews.index', ['reviews' => Review::query()->when($request->user()->hasRole('editor') && ! $request->user()->hasAnyRole('admin', 'super-admin'), fn ($q) => $q->whereHas('article', fn ($a) => $a->where('assigned_editor_id', $request->user()->id)))->with(['article:id,title,slug,deleted_at', 'reviewer:id,name,email'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))->latest()->paginate(25)->withQueryString()]);
    }

    public function show(Review $review): View
    {
        Gate::authorize('view', $review);

        return view('admin.reviews.show', ['review' => $review->load(['article', 'submission', 'reviewer:id,name,email', 'assignedBy:id,name', 'comments.user:id,name']),
            'reviewers' => User::query()->active()->whereHas('roles', fn ($q) => $q->whereIn('slug', ['reviewer', 'editor', 'admin', 'super-admin']))->orderBy('name')->get(['id', 'name'])]);
    }

    public function update(ReviewRequest $request, Review $review): RedirectResponse
    {
        Gate::authorize('update', $review);
        abort_if($review->article->workflow, 409, 'Use the manuscript workflow review actions.');
        $data = $request->validated();
        if (! $request->filled('reviewer_id')) {
            unset($data['reviewer_id']);
        }

        $review->update($data);

        if ($review->wasChanged(['reviewer_id', 'due_at'])) {
            ReviewAssigned::dispatch($review->refresh());
        }

        return back()->with('success', 'Review assignment updated.');
    }
}
