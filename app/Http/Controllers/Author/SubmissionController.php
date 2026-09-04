<?php

declare(strict_types=1);

namespace App\Http\Controllers\Author;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Submission;
use App\Services\ManuscriptWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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

    public function store(Request $request, Article $article): RedirectResponse
    {
        Gate::authorize('submit', $article);
        $action = in_array($article->workflow?->stage, ['minor_revision', 'major_revision'], true) ? 'revise' : 'submit';
        app(ManuscriptWorkflowService::class)->execute($article, $request->user(), $action, $request->all());

        return redirect()->route('workflow.show', $article)->with('success', 'Manuscript submitted.');
    }
}
