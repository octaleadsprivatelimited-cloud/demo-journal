<?php

declare(strict_types=1);

namespace App\Http\Controllers\Author;

use App\Enums\ArticleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Author\SubmitArticleRequest;
use App\Models\Article;
use App\Models\Submission;
use App\Services\ArticleWorkflowService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class SubmissionController extends Controller
{
    public function index(Request $request): View
    {
        $submissions = Submission::query()->whereHas('article', fn ($query) => $query->where('created_by_id', $request->user()->getKey()))
            ->with(['article:id,title,slug,status,deleted_at', 'version:id,article_id,version_number', 'reviews:id,submission_id,status,recommendation,completed_at'])
            ->latest('submitted_at')->paginate(15);

        return view('author.submissions.index', compact('submissions'));
    }

    public function store(SubmitArticleRequest $request, Article $article, ArticleWorkflowService $workflow): RedirectResponse
    {
        Gate::authorize('submit', $article);
        abort_unless(in_array($article->status, [ArticleStatus::Draft, ArticleStatus::RevisionRequired, ArticleStatus::Rejected], true), 409);

        $missing = collect([
            'title' => $article->title,
            'abstract' => $article->abstract,
            'content' => $article->content,
            'category' => $article->category_id,
        ])->filter(fn ($value) => blank($value))->keys();

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages(['confirmation' => 'Complete these required fields before submission: '.$missing->join(', ').'.']);
        }

        try {
            $workflow->submit($article, $request->user(), $request->string('cover_letter')->trim()->toString() ?: null);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['confirmation' => $exception->getMessage()]);
        }

        return redirect()->route('author.articles.show', $article)->with('success', 'Your manuscript has been submitted to the editorial office.');
    }
}
