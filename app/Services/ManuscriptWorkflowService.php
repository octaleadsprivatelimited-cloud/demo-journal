<?php

namespace App\Services;

use App\Enums\ArticleStatus;
use App\Enums\ReviewStatus;
use App\Enums\SubmissionStatus;
use App\Events\ArticleStatusChanged;
use App\Events\ArticleSubmitted;
use App\Events\ReviewAssigned;
use App\Models\Article;
use App\Models\JournalIssue;
use App\Models\ManuscriptWorkflow;
use App\Models\User;
use App\Models\WorkflowActivity;
use App\Models\WorkflowFile;
use App\Notifications\WorkflowNotification;
use App\Services\Contracts\DoiProvider;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ManuscriptWorkflowService
{
    public function __construct()
    {
        app(WorkflowSettings::class)->apply();
    }

    public function isAdmin(User $user): bool
    {
        return $user->hasAnyRole('admin', 'super-admin');
    }

    public function canEdit(User $user, Article $article): bool
    {
        return $user->isActive() && ($this->isAdmin($user) || ($user->hasRole('editor') && $article->assigned_editor_id === $user->id));
    }

    public function canView(User $user, Article $article): bool
    {
        return $this->canEdit($user, $article) || ($user->isActive() && $article->created_by_id === $user->id);
    }

    public function scope($query, User $user)
    {
        return $this->isAdmin($user) ? $query : ($user->hasRole('editor') ? $query->where('assigned_editor_id', $user->id) : $query->where('created_by_id', $user->id));
    }

    public function roleAllowed(User $user, Article $article, string $role): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        return match ($role) {
            'author' => $article->created_by_id === $user->id && $user->hasAnyRole('author', 'contributor'),'editor' => $this->canEdit($user, $article),'admin' => $this->isAdmin($user),default => false
        };
    }

    public function actions(Article $article, User $user): array
    {
        if (! $article->workflow && $article->status !== ArticleStatus::Draft) {
            return [];
        }
        $stage = $article->workflow?->stage ?? 'draft';

        return array_filter(config('workflow.actions'), fn ($a) => in_array($stage, $a['from'], true) && $this->roleAllowed($user, $article, $a['role']));
    }

    public function sequence(string $key): int
    {
        DB::table('workflow_sequences')->insertOrIgnore(['key' => $key, 'value' => 0]);
        $row = DB::table('workflow_sequences')->where('key', $key)->lockForUpdate()->first();
        DB::table('workflow_sequences')->where('key', $key)->update(['value' => $row->value + 1]);

        return $row->value + 1;
    }

    public function log(Article $article, User $actor, string $action, string $from, string $to, ?string $comments = null, array $data = [], bool $visible = true): void
    {
        WorkflowActivity::create(['article_id' => $article->id, 'actor_id' => $actor->id, 'role' => $actor->roles()->pluck('slug')->join(', '), 'action' => $action, 'from_stage' => $from, 'to_stage' => $to, 'comments' => $comments, 'data' => $data, 'author_visible' => $visible]);
    }

    private function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['workflow' => $message]);
        }
    }

    public function execute(Article $article, User $actor, string $action, array $input): ManuscriptWorkflow
    {
        return DB::transaction(function () use ($article, $actor, $action, $input) {
            $article = Article::whereKey($article->id)->lockForUpdate()->firstOrFail();
            $definition = config('workflow.actions.'.$action);
            $this->require(is_array($definition), 'Unknown workflow action.');
            abort_unless($this->roleAllowed($actor, $article, $definition['role']), 403);
            $workflow = $article->workflow;
            if (! $workflow) {
                $this->require($article->status === ArticleStatus::Draft && $action === 'submit', 'This existing record must be adopted by an administrator before entering the workflow.');
                $workflow = ManuscriptWorkflow::create(['article_id' => $article->id, 'stage' => 'draft', 'data' => []]);
            }
            $from = $workflow->stage;
            $this->require(in_array($from, $definition['from'], true), 'This action is no longer available. Refresh the manuscript.');
            $rules = ['comments' => ['nullable', 'string', 'max:10000']];
            if (in_array($action, ['return', 'reject', 'minor_revision', 'major_revision', 'accept', 'proof_corrections'], true)) {
                $rules['comments'] = ['required', 'string', 'max:10000'];
            }
            if (in_array($action, ['minor_revision', 'major_revision', 'assign_reviewer', 'send_proof'], true)) {
                $rules['deadline'] = ['required', 'date', 'after:now'];
            }
            if ($action === 'assign_reviewer') {
                $rules['reviewer_id'] = ['required', 'integer', 'exists:users,id'];
                $rules['invitation_deadline'] = ['required', 'date', 'after:now', 'before_or_equal:deadline'];
                $rules['editor_message'] = ['required', 'string', 'max:5000'];
            }
            if (in_array($action, ['pass_similarity', 'save_similarity'], true)) {
                $rules['similarity'] = ['required', 'numeric', 'between:0,100'];
                $rules['comments'] = ['required', 'string', 'max:10000'];
                $rules['report'] = ['required', 'file', 'mimes:pdf', 'extensions:pdf', 'max:20480'];
            }
            if ($action === 'save_similarity') {
                $rules['similarity_status'] = ['required', Rule::in(['pending', 'passed', 'needs_clarification', 'failed'])];
            }
            if (in_array($action, ['submit', 'revise'], true)) {
                foreach (['original', 'exclusive', 'authors_approve', 'ethics', 'conflicts'] as $key) {
                    $rules['declarations.'.$key] = ['accepted'];
                }
                if ($action === 'revise') {
                    $rules['manuscript'] = ['required', 'file', 'mimes:pdf,doc,docx', 'extensions:pdf,doc,docx', 'max:20480'];
                    $rules['response'] = ['required', 'file', 'mimes:pdf,doc,docx', 'extensions:pdf,doc,docx', 'max:20480'];
                }
            }
            foreach (['manuscript', 'cover_letter', 'supplementary', 'edited_manuscript', 'galley', 'correction', 'final_pdf'] as $file) {
                $rules[$file] ??= ['nullable', 'file', 'mimes:pdf,doc,docx', 'extensions:pdf,doc,docx', 'max:20480'];
            }
            $rules['supplementary'] = ['nullable', 'array', 'max:10'];
            $rules['supplementary.*'] = ['file', 'mimes:pdf,doc,docx', 'extensions:pdf,doc,docx', 'max:20480'];
            if ($action === 'save_copyediting') {
                $rules['edited_manuscript'][0] = 'required';
            }
            if ($action === 'send_proof') {
                $rules['galley'] = ['required', 'file', 'mimes:pdf', 'extensions:pdf', 'max:20480'];
            }
            if ($action === 'proof_corrections') {
                $rules['correction'][0] = 'required';
            }
            if ($action === 'verify_metadata') {
                foreach (['title', 'abstract', 'publication_type', 'volume', 'issue', 'pages'] as $field) {
                    $rules[$field] = ['required', 'string', 'max:'.($field === 'abstract' ? 10000 : 240)];
                }
                $rules['year'] = ['required', 'integer', 'between:1900,2200'];
                $rules['publication_date'] = ['required', 'date'];
                $rules['keywords'] = ['required', 'string', 'max:3000'];
                $rules['references'] = ['required', 'string', 'max:20000'];
                $rules['final_pdf'] = ['required', 'file', 'mimes:pdf', 'extensions:pdf', 'max:20480'];
                $rules['journal_issue_id'] = ['required', 'integer', 'exists:journal_issues,id'];
            }
            if (in_array($action, ['register_doi', 'activate_doi'], true)) {
                $rules['doi'] = ['required', 'string', 'max:255', 'regex:~^10\.\d{4,9}/\S+$~'];
                $rules['agency'] = ['required', 'string', 'max:120'];
                $rules['evidence_url'] = ['required', 'url:http,https', 'max:2000'];
                $rules['confirmed'] = ['accepted'];
            }
            if ($action === 'save_indexing') {
                $rules['service_id'] = ['required', 'exists:indexing_services,id'];
                $rules['indexing_status'] = ['required', Rule::in(['not_submitted', 'submitted', 'under_review', 'indexed', 'rejected'])];
                $rules['indexed_url'] = ['nullable', 'url:http,https', 'max:2000'];
                if (($input['indexing_status'] ?? null) === 'indexed') {
                    $rules['indexed_url'][0] = 'required';
                }
            }
            $allowedFiles = match ($action) {
                'submit' => ['manuscript', 'cover_letter', 'supplementary'],'revise' => ['manuscript', 'cover_letter', 'supplementary', 'response'],'pass_similarity','save_similarity' => ['report'],'save_copyediting' => ['edited_manuscript'],'send_proof' => ['galley'],'proof_corrections' => ['correction'],'verify_metadata' => ['final_pdf'],default => []
            };
            foreach (['manuscript', 'cover_letter', 'supplementary', 'response', 'report', 'edited_manuscript', 'galley', 'correction', 'final_pdf'] as $key) {
                if (! in_array($key, $allowedFiles, true)) {
                    unset($rules[$key],$input[$key]);
                }
            }
            $v = Validator::make($input, $rules)->validate();
            $data = $workflow->data ?? [];
            if (in_array($action, ['submit', 'revise'], true)) {
                $this->require(filled($article->title) && filled($article->abstract) && ! empty($article->keywords), 'Title, abstract and keywords are required.');
                $this->require($article->authors()->exists() && $article->authors()->wherePivot('is_corresponding', true)->exists(), 'Add authors and select a corresponding author.');
                foreach ($article->authors as $author) {
                    $this->require(filled($author->name) && filled($author->email) && filled($author->organization), 'Each author needs a name, email and institution.');
                }
                $this->require(isset($v['manuscript']) || WorkflowFile::where('article_id', $article->id)->where('purpose', 'manuscript')->exists(), 'Upload a manuscript.');
                $this->require(isset($v['cover_letter']) || WorkflowFile::where('article_id', $article->id)->where('purpose', 'cover_letter')->exists(), 'Upload a cover letter.');
                if (! $workflow->manuscript_id) {
                    $seq = $this->sequence('manuscript-'.now()->year);
                    $workflow->manuscript_id = strtr(config('workflow.manuscript_format'), ['{year}' => now()->year, '{sequence}' => str_pad($seq, 4, '0', STR_PAD_LEFT)]);
                }
                $workflow->revision = ((int) $article->submissions()->max('round')) + 1;
                foreach (['manuscript', 'cover_letter', 'supplementary'] as $purpose) {
                    if (! isset($v[$purpose])) {
                        $prior = WorkflowFile::where('article_id', $article->id)->where('purpose', $purpose);
                        $round = (clone $prior)->max('round');
                        $previous = $purpose === 'supplementary' ? $prior->where('round', $round)->get() : $prior->latest('id')->limit(1)->get();
                        foreach ($previous as $file) {
                            $copy = $file->replicate();
                            $copy->round = $workflow->revision;
                            $copy->save();
                        }
                    }
                }
                $version = app(ArticleVersionService::class)->snapshot($article, $actor, $action === 'revise' ? 'Revised manuscript' : 'Manuscript submitted');
                $submission = $article->submissions()->create(['article_version_id' => $version->id, 'submitted_by_id' => $actor->id, 'round' => ((int) $article->submissions()->max('round')) + 1, 'status' => SubmissionStatus::Pending, 'submitted_at' => now(), 'cover_letter' => $v['comments'] ?? null]);
                $article->forceFill(['status' => ArticleStatus::Submitted, 'submitted_at' => $article->submitted_at ?? now()])->save();
                $data['declarations'] = $v['declarations'];
                $data['current_submission_id'] = $submission->id;
                ArticleSubmitted::dispatch($article, $submission);
            }
            if ($action === 'approve_check') {
                foreach (['manuscript', 'cover_letter', 'authors', 'abstract', 'keywords', 'references', 'documents', 'format'] as $item) {
                    $this->require(($input['checklist'][$item] ?? null) === '1', 'Complete every initial-check item.');
                }
                $this->require(WorkflowFile::where('article_id', $article->id)->where('purpose', 'manuscript')->exists() && WorkflowFile::where('article_id', $article->id)->where('purpose', 'cover_letter')->exists(), 'Manuscript and cover letter files are required.');
                $data['checklist'] = $input['checklist'];
            }
            if (in_array($action, ['pass_similarity', 'save_similarity'], true)) {
                $data['similarity'] = ['percentage' => $v['similarity'], 'status' => $action === 'pass_similarity' ? 'passed' : $v['similarity_status'], 'remarks' => $v['comments'], 'checked_by' => $actor->id, 'checked_at' => now()->toIso8601String()];
            }
            if ($action === 'assign_reviewer') {
                $reviewer = User::findOrFail($v['reviewer_id']);
                $this->require($reviewer->isActive() && $reviewer->hasRole('reviewer') && data_get($reviewer->reviewer_profile, 'available', true), 'Select an active, available reviewer.');
                $this->require(! $article->isOwnedBy($reviewer), 'An author cannot review their own manuscript.');
                $sid = $data['current_submission_id'] ?? null;
                $this->require(! $article->reviews()->where('submission_id', $sid)->where('reviewer_id', $reviewer->id)->whereIn('status', ['assigned', 'in_progress', 'completed'])->exists(), 'This reviewer already has an invitation or review in this round.');
                $review = $article->reviews()->create(['submission_id' => $sid, 'reviewer_id' => $reviewer->id, 'assigned_by_id' => $actor->id, 'status' => ReviewStatus::Assigned, 'due_at' => $v['deadline'], 'invitation_deadline' => $v['invitation_deadline'], 'editor_message' => $v['editor_message']]);
                ReviewAssigned::dispatch($review);
            }
            if (in_array($action, ['accept', 'minor_revision', 'major_revision'], true) && in_array($from, ['under_review', 'reviewer_recheck'], true)) {
                $this->require($article->reviews()->where('submission_id', $data['current_submission_id'] ?? null)->where('status', 'completed')->exists(), 'At least one completed review for this submission is required before the final decision.');
            }
            if (in_array($action, ['minor_revision', 'major_revision'], true)) {
                $data['revision_request'] = ['type' => $action, 'comments' => $v['comments'], 'deadline' => $v['deadline'], 'requested_at' => now()->toIso8601String()];
                $article->forceFill(['status' => ArticleStatus::RevisionRequired])->save();
            }
            if ($action === 'return') {
                $article->forceFill(['status' => ArticleStatus::RevisionRequired])->save();
            }
            if ($action === 'accept') {
                $data['acceptance'] = ['date' => now()->toDateString(), 'editor' => $actor->name, 'author' => $article->authors()->pluck('name')->join(', '), 'title' => $article->title, 'comments' => $v['comments']];
                $article->forceFill(['status' => ArticleStatus::Approved, 'approved_at' => now(), 'accepted_date' => today()])->save();
                app(ArticleVersionService::class)->snapshot($article, $actor, 'Final accepted manuscript');
            }
            if (in_array($action, ['accept', 'reject', 'minor_revision', 'major_revision', 'return'], true)) {
                $latest = $article->submissions()->latest('round')->first();
                $latest?->update(['status' => match ($action) {
                    'accept' => SubmissionStatus::Accepted,'reject' => SubmissionStatus::Rejected,default => SubmissionStatus::RevisionRequested
                }, 'decision_at' => now()]);
            }
            if ($action === 'reject') {
                $article->forceFill(['status' => ArticleStatus::Rejected, 'rejected_at' => now()])->save();
            }
            if ($action === 'start_copyediting') {
                $data['copyediting_status'] = 'in_progress';
            }
            if ($action === 'send_proof') {
                $this->require(WorkflowFile::where('article_id', $article->id)->where('purpose', 'edited_manuscript')->exists(), 'Upload an edited manuscript before sending proof.');
                $data['copyediting_status'] = 'completed';
            }
            if ($action === 'approve_proof') {
                $data['proof_approval'] = ['by' => $actor->id, 'at' => now()->toIso8601String()];
            }
            if ($action === 'verify_metadata') {
                $issue = JournalIssue::with('volume')->findOrFail($v['journal_issue_id']);
                $this->require((string) $issue->number === $v['issue'] && (string) $issue->volume->number === $v['volume'] && (int) $issue->volume->year === (int) $v['year'], 'Volume, issue and year must match the selected archive issue.');
                $this->require($article->authors()->wherePivot('is_corresponding', true)->exists(), 'A corresponding author is required.');
                foreach ($article->authors as $author) {
                    $this->require(filled($author->organization) && filled($author->email), 'Verify author affiliations and email addresses.');
                }
                $article->forceFill(['title' => $v['title'], 'abstract' => $v['abstract'], 'publication_type' => $v['publication_type'], 'volume' => $v['volume'], 'issue' => $v['issue'], 'journal_issue_id' => $v['journal_issue_id'], 'keywords' => array_values(array_filter(array_map('trim', explode(',', $v['keywords'])))), 'references' => array_values(array_filter(preg_split('/\r?\n/', $v['references'])))])->save();
                $data['metadata'] = ['year' => $v['year'], 'pages' => $v['pages'], 'publication_date' => $v['publication_date'], 'verified_by' => $actor->id, 'verified_at' => now()->toIso8601String()];
            }
            if ($action === 'article_number') {
                $this->require(blank($article->article_number), 'An article number already exists and cannot be replaced.');
                do {
                    $seq = $this->sequence('article-'.$data['metadata']['year']);
                    $number = strtr(config('workflow.article_format'), ['{year}' => $data['metadata']['year'], '{volume}' => str_pad($article->volume, 2, '0', STR_PAD_LEFT), '{issue}' => str_pad($article->issue, 2, '0', STR_PAD_LEFT), '{sequence}' => str_pad($seq, 3, '0', STR_PAD_LEFT)]);
                } while (Article::withTrashed()->where('article_number', $number)->exists());
                $article->forceFill(['article_number' => $number])->save();
            }
            if ($action === 'prepare_doi') {
                $data['doi']['prepared_metadata'] = app(DoiProvider::class)->prepare($article);
            }
            if (in_array($action, ['prepare_doi', 'submit_doi', 'register_doi', 'activate_doi'], true)) {
                $data['doi'] = array_merge($data['doi'] ?? [], ['status' => $definition['to'], 'updated_at' => now()->toIso8601String()]);
                if (isset($v['doi'])) {
                    $this->sequence('doi-registration-lock');
                    $this->require(blank($article->doi) || $article->doi === $v['doi'], 'An existing DOI cannot be replaced by this action.');
                    $this->require(! Article::where('doi', $v['doi'])->whereKeyNot($article->id)->exists(), 'This DOI is already assigned to another article.');
                    $article->forceFill(['doi' => $v['doi']])->save();
                    $data['doi'] += ['agency' => $v['agency']];
                    $data['doi']['evidence_url'] = $v['evidence_url'];
                    $data['doi']['confirmed_by'] = $actor->id;
                }
            }
            if ($action === 'publish') {
                $this->require(filled($article->doi) && filled($article->article_number) && ! empty($data['proof_approval']) && ! empty($data['metadata']) && ! empty($data['acceptance']) && data_get($data, 'doi.status') === 'ready_to_publish', 'Acceptance, proof approval, verified metadata, article number and DOI are required.');
                $this->require(filled($article->pdf_path) && Storage::disk('local')->exists($article->pdf_path), 'A final publication PDF is required.');
                $date = Carbon::parse($data['metadata']['publication_date']);
                $this->require(! $date->isFuture(), 'Publication date cannot be in the future when publishing now.');
                $old = $article->status;
                $article->forceFill(['status' => ArticleStatus::Published, 'published_at' => $date, 'scheduled_for' => null])->save();
                ArticleStatusChanged::dispatch($article, $old, ArticleStatus::Published, $actor);
            }
            if ($action === 'save_indexing') {
                $data['indexing'][(string) $v['service_id']] = ['status' => $v['indexing_status'], 'url' => $v['indexed_url'] ?? null, 'notes' => $v['comments'] ?? null, 'date' => now()->toDateString()];
            }
            if ($action === 'indexed') {
                $this->require(collect($data['indexing'] ?? [])->contains(fn ($r) => $r['status'] === 'indexed'), 'Record at least one verified indexed result first.');
            }
            foreach (['manuscript', 'cover_letter', 'supplementary', 'response', 'report', 'edited_manuscript', 'galley', 'correction', 'final_pdf'] as $purpose) {
                if (! isset($v[$purpose])) {
                    continue;
                }
                $uploads = $purpose === 'supplementary' ? $v[$purpose] : [$v[$purpose]];
                foreach ($uploads as $file) {
                    $path = $file->store('workflow/'.$article->id, 'local');
                    WorkflowFile::create(['article_id' => $article->id, 'uploaded_by_id' => $actor->id, 'article_version_id' => $version->id ?? null, 'purpose' => $purpose, 'path' => $path, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(), 'checksum' => hash_file('sha256', $file->getRealPath()), 'size' => $file->getSize(), 'round' => $workflow->revision]);
                    if ($purpose === 'final_pdf') {
                        $article->forceFill(['pdf_path' => $path])->save();
                    }
                }
            }
            $workflow->stage = $definition['to'] ?? $from;
            $workflow->data = $data;
            if (in_array($action, ['minor_revision', 'major_revision', 'send_proof'], true)) {
                $workflow->deadline = $v['deadline'];
            } elseif ($definition['to'] !== null) {
                $workflow->deadline = null;
            }
            $workflow->save();
            $article->touch();
            $this->log($article, $actor, $action, $from, $workflow->stage, $v['comments'] ?? null, collect($v)->except(['manuscript', 'cover_letter', 'supplementary', 'response', 'report', 'edited_manuscript', 'galley', 'correction', 'final_pdf'])->all(), ! in_array($action, ['pass_similarity', 'save_similarity', 'assign_reviewer'], true));
            $this->notify($article, $definition['label'], $actor);

            return $workflow;
        });
    }

    public function notify(Article $article,string $message,User $actor): void
    {
        $ids = [$article->created_by_id, $article->assigned_editor_id];
        $admins = User::active()->whereHas('roles',fn ($q) => $q->whereIn('slug',['admin', 'super-admin']))->pluck('id');
        foreach (User::active()->whereIn('id',array_unique(array_filter(array_merge($ids,$admins->all()))))->get() as $user) {
            if ($user->id !== $actor->id) {
                $user->notify(new WorkflowNotification($article->id,$message));
            }
        }
    }
}
