<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ArticleStatus;
use App\Http\Controllers\Controller;
use App\Models\{Article, User};
use App\Services\{ArticleWorkflowService, ManuscriptWorkflowService};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ReviewerAssignmentController extends Controller
{
    public function index(Request $request)
    {
        $query = Article::query()->whereIn('status', [ArticleStatus::Submitted, ArticleStatus::UnderReview, ArticleStatus::RevisionRequired]);
        if (! $request->user()->hasAnyRole('admin', 'super-admin')) {
            $query->where('assigned_editor_id', $request->user()->id);
        }
        $request->validate(['q' => ['nullable', 'string', 'max:200']]);
        return view('admin.assign-reviewer.index', ['articles' => $query->when($request->filled('q'), fn ($q) => $q->search($request->input('q')))->with(['workflow', 'assignedEditor'])->latest()->paginate(15)->withQueryString()]);
    }

    private function allowed(Article $article): bool
    {
        return $article->workflow
            ? in_array($article->workflow->stage, ['reviewer_assignment', 'under_review', 'reviewer_recheck'])
            : in_array($article->status, [ArticleStatus::Submitted, ArticleStatus::UnderReview]);
    }

    public function show(Request $request, Article $article, ManuscriptWorkflowService $workflow)
    {
        abort_unless($workflow->canEdit($request->user(), $article), 403);
        $article->load(['workflow', 'reviews.reviewer']);
        $round = $article->workflow ? data_get($article->workflow->data, 'current_submission_id') : $article->submissions()->latest('round')->value('id');
        $assigned = $article->reviews()->where('submission_id', $round)->whereIn('status', ['assigned', 'in_progress', 'completed'])->pluck('reviewer_id');
        $reviewers = User::active()->whereHas('roles', fn ($q) => $q->where('slug', 'reviewer'))
            ->where('id', '!=', $article->created_by_id)->whereNotIn('id', $article->authors()->whereNotNull('user_id')->pluck('user_id'))
            ->whereNotIn('id', $assigned)->withCount(['reviews as active_reviews_count' => fn ($q) => $q->whereIn('status', ['assigned', 'in_progress'])])->orderBy('name')->get()
            ->filter(fn ($user) => data_get($user->reviewer_profile, 'available', true));
        return view('admin.assign-reviewer.show', ['article' => $article, 'reviewers' => $reviewers, 'ready' => $this->allowed($article)]);
    }

    public function store(Request $request, Article $article, ManuscriptWorkflowService $workflow, ArticleWorkflowService $legacy)
    {
        abort_unless($workflow->canEdit($request->user(), $article), 403);
        abort_unless($this->allowed($article), 409, 'Complete the editorial checks before inviting reviewers.');
        $data = $request->validate(['reviewer_id' => ['required', 'integer', 'exists:users,id'], 'deadline' => ['required', 'date', 'after:today'], 'invitation_deadline' => ['required', 'date', 'after:today', 'before_or_equal:deadline'], 'editor_message' => ['required', 'string', 'max:5000']]);
        if ($article->workflow) {
            $workflow->execute($article, $request->user(), 'assign_reviewer', $data);
        } else {
            try {
                $review = $legacy->assignReviewer($article, User::findOrFail($data['reviewer_id']), $request->user(), Carbon::parse($data['deadline']));
                $review->update(['invitation_deadline' => $data['invitation_deadline'], 'editor_message' => $data['editor_message']]);
            } catch (\DomainException $error) {
                throw ValidationException::withMessages(['reviewer_id' => $error->getMessage()]);
            }
        }
        return redirect()->route('admin.assign-reviewer.show', $article)->with('success', 'Reviewer assigned. The invitation is recorded and queued for notification.');
    }
}
