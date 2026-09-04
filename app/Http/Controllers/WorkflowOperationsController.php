<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Review;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ReviewAssignedNotification;
use App\Notifications\WorkflowNotification;
use App\Services\AuditService;
use App\Services\ManuscriptWorkflowService;
use App\Services\WorkflowSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class WorkflowOperationsController extends Controller
{
    public function staff(Request $request)
    {
        abort_unless($request->user()->hasAnyRole('admin', 'super-admin', 'editor'), 403);
        $people = User::whereHas('roles', fn ($q) => $q->whereIn('slug', ['reviewer', 'editor']))->with('roles')->withCount(['reviews as active_reviews_count' => fn ($q) => $q->whereIn('status', ['assigned', 'in_progress']), 'reviews as completed_reviews_count' => fn ($q) => $q->where('status', 'completed')])->orderBy('name')->paginate(20);

        return view('workflow.staff', compact('people'));
    }

    public function staffUpdate(Request $request, User $user)
    {
        abort_unless($request->user()->hasAnyRole('admin', 'super-admin'), 403);
        abort_unless($user->hasAnyRole('editor', 'reviewer') && ! $user->hasAnyRole('admin', 'super-admin'), 403);
        $v = $request->validate(['name' => ['required', 'string', 'max:200'], 'organization' => ['nullable', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:50'], 'department' => ['nullable', 'string', 'max:255'], 'country' => ['nullable', 'string', 'max:100'], 'expertise' => ['nullable', 'string', 'max:2000'], 'research_interests' => ['nullable', 'string', 'max:2000'], 'orcid' => ['nullable', 'regex:/^\d{4}-\d{4}-\d{4}-\d{3}[\dX]$/'], 'available' => ['required', 'boolean'], 'is_active' => ['required', 'boolean']]);
        DB::transaction(function () use ($user, $v, $request) {
            $old = $user->only(['name', 'organization', 'phone', 'is_active', 'reviewer_profile']);
            $user->update(['name' => $v['name'], 'organization' => $v['organization'] ?? null, 'phone' => $v['phone'] ?? null, 'is_active' => (bool) $v['is_active'], 'status' => $v['is_active'] ? 'active' : 'inactive', 'reviewer_profile' => collect($v)->only(['department', 'country', 'expertise', 'research_interests', 'orcid', 'available'])->all()]);
            app(AuditService::class)->record($user, 'workflow.staff_updated', $old, $v, 'Journal staff profile updated', $request->user());
        });

        return back()->with('success', 'Staff profile updated.');
    }

    public function settings(Request $request)
    {
        abort_unless($request->user()->hasRole('super-admin'), 403);

        return view('workflow.settings', ['settings' => app(WorkflowSettings::class)->values()]);
    }

    public function saveSettings(Request $request)
    {
        abort_unless($request->user()->hasRole('super-admin'), 403);
        $v = $request->validate(['manuscript_format' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9{}-]+$/'], 'article_format' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9{}-]+$/'], 'deadlines' => ['required', 'array:invitation,review,minor,major,proof'], 'deadlines.*' => ['required', 'integer', 'between:1,365'], 'notification_intro' => ['required', 'string', 'max:500'], 'doi_agency' => ['required', 'string', 'max:120']]);
        foreach (['manuscript_format', 'article_format'] as $key) {
            abort_unless(str_contains($v[$key], '{sequence}') && str_contains($v[$key], '{year}'), 422);
        }foreach (['invitation', 'review', 'minor', 'major', 'proof'] as $key) {
            abort_unless(isset($v['deadlines'][$key]), 422);
        }DB::transaction(function () use ($v, $request) {
            $old = app(WorkflowSettings::class)->values();
            $setting = Setting::updateOrCreate(['key' => 'workflow.configuration'], ['value' => $v, 'group' => 'workflow', 'is_public' => false]);
            app(AuditService::class)->record($setting, 'workflow.settings_updated', $old, $v, null, $request->user());
        });

        return back()->with('success', 'Workflow settings saved. Existing identifiers are unchanged.');
    }

    public function deadline(Request $request, Article $article)
    {
        $service = app(ManuscriptWorkflowService::class);
        abort_unless($service->canEdit($request->user(), $article), 403);
        $v = $request->validate(['deadline' => ['required', 'date', 'after:now'], 'review_id' => ['nullable', 'integer'], 'kind' => ['required', Rule::in(['manuscript', 'review', 'invitation'])], 'comments' => ['required', 'string', 'max:5000']]);
        DB::transaction(function () use ($article, $service, $request, $v) {
            $a = Article::whereKey($article->id)->lockForUpdate()->firstOrFail();
            $w = $a->workflow;
            abort_unless($w, 409);
            if ($v['kind'] === 'manuscript') {
                abort_unless(in_array($w->stage, ['minor_revision', 'major_revision', 'proofing']), 409);
                $d = $w->data ?? [];
                if (isset($d['revision_request'])) {
                    $d['revision_request']['deadline'] = $v['deadline'];
                }$w->update(['deadline' => $v['deadline'], 'data' => $d]);
            } else {
                $review = $a->reviews()->whereKey($v['review_id'] ?? 0)->lockForUpdate()->firstOrFail();
                abort_unless(in_array($review->status->value, ['assigned', 'in_progress']), 409);
                $review->update([$v['kind'] === 'invitation' ? 'invitation_deadline' : 'due_at' => $v['deadline']]);
                $review->reviewer->notify(new WorkflowNotification($a->id, 'Review deadline updated'));
            }$service->log($a, $request->user(), 'deadline_updated', $w->stage, $w->stage, $v['comments'], $v);
            $service->notify($a, 'Manuscript deadline updated', $request->user());
        });

        return back()->with('success', 'Deadline updated and logged.');
    }

    public function reopen(Request $request, Review $review)
    {
        $service = app(ManuscriptWorkflowService::class);
        abort_unless($service->canEdit($request->user(), $review->article), 403);
        $v = $request->validate(['comments' => ['required', 'string', 'max:5000'], 'deadline' => ['required', 'date', 'after:now']]);
        DB::transaction(function () use ($review, $service, $request, $v) {
            $a = Article::whereKey($review->article_id)->lockForUpdate()->firstOrFail();
            $review = Review::whereKey($review->id)->lockForUpdate()->firstOrFail();
            $w = $a->workflow;
            abort_unless($w && in_array($w->stage, ['under_review', 'reviewer_recheck']) && $review->status->value === 'completed' && $review->submission_id == data_get($w->data, 'current_submission_id'), 409);
            $service->log($a, $request->user(), 'review_reopened', $w->stage, $w->stage, $v['comments'], ['review_id' => $review->id, 'previous_report' => $review->toArray()], false);
            $review->update(['status' => 'in_progress', 'completed_at' => null, 'due_at' => $v['deadline'], 'recommendation' => null, 'comments_to_author' => null, 'confidential_comments' => null, 'review_file_path' => null]);
            $review->reviewer->notify(new ReviewAssignedNotification($review));
        });

        return back()->with('success', 'Review reopened; its previous report remains in the staff activity record.');
    }

    public function reviewFile(Request $request, Review $review)
    {
        abort_unless(app(ManuscriptWorkflowService::class)->canEdit($request->user(),$review->article) || $request->user()->id === $review->reviewer_id, 403);
        abort_unless($review->review_file_path && Storage::disk('local')->exists($review->review_file_path),404);

        return Storage::disk('local')->download($review->review_file_path);
    }
}
