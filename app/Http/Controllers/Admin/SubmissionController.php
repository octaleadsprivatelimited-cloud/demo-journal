<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SubmissionRequest;
use App\Models\Submission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class SubmissionController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Submission::class);

        return view('admin.submissions.index', ['submissions' => Submission::query()->with(['article:id,title,slug,status,deleted_at', 'submitter:id,name,email'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))->latest('submitted_at')->paginate(25)->withQueryString()]);
    }

    public function show(Submission $submission): View
    {
        Gate::authorize('view', $submission);

        return view('admin.submissions.show', ['submission' => $submission->load(['article.authors', 'submitter:id,name,email', 'version', 'reviews.reviewer:id,name'])]);
    }

    public function update(SubmissionRequest $request, Submission $submission): RedirectResponse
    {
        Gate::authorize('update', $submission);
        $status = $request->enum('status', SubmissionStatus::class);
        $submission->update(['status' => $status,
            'decision_at' => in_array($status, [SubmissionStatus::Accepted, SubmissionStatus::Rejected, SubmissionStatus::Withdrawn], true) ? now() : null]);

        return back()->with('success', 'Submission record updated. Use article actions for publication decisions.');
    }
}
