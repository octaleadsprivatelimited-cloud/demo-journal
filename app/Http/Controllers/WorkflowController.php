<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\IndexingService;
use App\Models\JournalIssue;
use App\Models\ManuscriptWorkflow;
use App\Models\User;
use App\Models\WorkflowActivity;
use App\Models\WorkflowFile;
use App\Services\AcceptanceLetterPdf;
use App\Services\ManuscriptWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class WorkflowController extends Controller
{
    public function __construct(private ManuscriptWorkflowService $workflow) {}

    public function index(Request $request)
    {
        $user = $request->user();
        if ($user->hasRole('reviewer') && ! $user->hasAnyRole('author', 'editor', 'admin', 'super-admin')) {
            return redirect()->route('reviewer.dashboard');
        }
        $query = $this->workflow->scope(Article::query(), $user);
        $counts = (clone $query)->leftJoin('manuscript_workflows', 'articles.id', '=', 'manuscript_workflows.article_id')->selectRaw('coalesce(manuscript_workflows.stage, articles.status) as stage, count(*) as total')->groupByRaw('coalesce(manuscript_workflows.stage, articles.status)')->pluck('total', 'stage');
        $selectedStages = array_values(array_intersect((array) $request->input('stages', []), array_merge(config('workflow.stages'), ['revision_required', 'approved'])));
        $query->when($selectedStages, fn ($q) => $q->where(fn ($q) => $q->whereHas('workflow', fn ($w) => $w->whereIn('stage', $selectedStages))->orWhere(fn ($q) => $q->whereDoesntHave('workflow')->whereIn('status', $selectedStages))));
        $listing = $query->with(['workflow', 'creator', 'assignedEditor', 'reviews.reviewer'])->when($request->filled('stage'), fn ($q) => $q->whereHas('workflow', fn ($w) => $w->where('stage', $request->input('stage'))))->when($request->filled('q'), fn ($q) => $q->where(fn ($q) => $q->where('title', 'like', '%'.$request->input('q').'%')->orWhereHas('workflow', fn ($w) => $w->where('manuscript_id', 'like', '%'.$request->input('q').'%'))))->when($request->filled('author'), fn ($q) => $q->whereHas('creator', fn ($u) => $u->where('name', 'like', '%'.$request->input('author').'%')))->when($request->filled('reviewer'), fn ($q) => $q->whereHas('reviews', fn ($r) => $r->where('reviewer_id', $request->input('reviewer'))))->when($request->filled('type'), fn ($q) => $q->where('publication_type', $request->input('type')))->when($request->filled('date'), fn ($q) => $q->whereDate('submitted_at', $request->input('date')))->when($request->filled('publication_status'), fn ($q) => $q->where('status', $request->input('publication_status')))->latest('articles.updated_at');
        if ($request->input('export') === 'csv') {
            return response()->streamDownload(function () use ($listing) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Manuscript ID', 'Title', 'Author', 'Submitted', 'Stage', 'Publication status', 'Updated'], ',', '"', '');
                foreach ($listing->lazy(100) as $article) {
                    $cells = [$article->workflow?->manuscript_id, $article->title, $article->creator?->name, $article->submitted_at?->toDateString(), $article->workflow?->stage ?? $article->status->value, $article->status->value, $article->updated_at->toDateString()];
                    fputcsv($out, array_map(fn ($value) => preg_match('/^[=+@\-\t\r]/', (string) $value) ? "'".$value : $value, $cells), ',', '"', '');
                }fclose($out);
            }, 'manuscript-report.csv', ['Content-Type' => 'text/csv']);
        }
        $articles = $listing->paginate(20)->withQueryString();

        return view('workflow.index', compact('articles', 'counts'));
    }

    public function show(Request $request, Article $article)
    {
        abort_unless($this->workflow->canView($request->user(), $article), 403);
        $article->load(['workflow', 'authors', 'assignedEditor', 'creator']);
        $editor = $this->workflow->canEdit($request->user(), $article);
        abort_unless($editor || in_array($request->input('tab', 'overview'), ['overview', 'files', 'reviews', 'revisions', 'proofs', 'publication', 'activity']), 403);
        $activities = WorkflowActivity::where('article_id', $article->id)->when(! $editor, fn ($q) => $q->where('author_visible', true))->with('actor')->latest('id')->get();
        $files = WorkflowFile::where('article_id', $article->id)->when(! $editor, fn ($q) => $q->whereNotIn('purpose', ['report', 'edited_manuscript']))->with('uploader')->latest()->get();
        $reviews = $article->reviews()->with('reviewer')->when(! $editor, fn ($q) => $q->where('status', 'completed'))->get();
        $reviewers = $editor ? User::active()->whereHas('roles', fn ($q) => $q->where('slug', 'reviewer'))->withCount(['reviews as active_reviews_count' => fn ($q) => $q->whereIn('status', ['assigned', 'in_progress']), 'reviews as completed_reviews_count' => fn ($q) => $q->where('status', 'completed')])->get() : collect();
        $actions = $this->workflow->actions($article, $request->user());

        return view('workflow.show', compact('article', 'editor', 'activities', 'files', 'reviews', 'reviewers', 'actions') + ['issues' => JournalIssue::with('volume')->get(), 'services' => IndexingService::all(), 'editors' => $this->workflow->isAdmin($request->user()) ? User::active()->whereHas('roles', fn ($q) => $q->where('slug', 'editor'))->get() : collect()]);
    }

    public function action(Request $request, Article $article)
    {
        $this->workflow->execute($article, $request->user(), $request->string('action')->toString(), $request->all());

        return redirect()->route('workflow.show', $article)->with('success', 'Workflow action recorded.');
    }

    public function download(Request $request, WorkflowFile $file)
    {
        $article = $file->article;
        $staff = $this->workflow->canEdit($request->user(), $article);
        $owner = $article->created_by_id === $request->user()->id;
        $reviewer = $article->reviews()->where('reviewer_id', $request->user()->id)->whereIn('status', ['in_progress', 'completed'])->whereHas('submission', fn ($q) => $q->where('round', $file->round))->exists();
        abort_unless($staff || ($owner && ! in_array($file->purpose, ['report', 'edited_manuscript'])) || ($reviewer && in_array($file->purpose, ['manuscript', 'supplementary', 'response'])), 403);
        abort_unless(Storage::disk('local')->exists($file->path), 404);

        return Storage::disk('local')->download($file->path, $file->original_name, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function assignEditor(Request $request, Article $article)
    {
        abort_unless($this->workflow->isAdmin($request->user()), 403);
        $v = $request->validate(['editor_id' => ['required', 'exists:users,id'], 'owner_id' => ['nullable', 'exists:users,id'], 'comments' => ['required', 'string', 'max:5000']]);
        $editor = User::findOrFail($v['editor_id']);
        abort_unless($editor->isActive() && $editor->hasRole('editor'), 422);
        DB::transaction(function () use ($article, $v, $request) {
            $a = Article::whereKey($article->id)->lockForUpdate()->firstOrFail();
            $a->assigned_editor_id = $v['editor_id'];
            if (! empty($v['owner_id'])) {
                $owner = User::findOrFail($v['owner_id']);
                abort_unless($owner->isActive() && $owner->hasAnyRole('author', 'contributor'), 422);
                $a->created_by_id = $owner->id;
            }$a->save();
            $stage = $a->workflow?->stage ?? $a->status->value;
            $this->workflow->log($a, $request->user(), 'assignment_updated', $stage, $stage, $v['comments'], $v);
            $this->workflow->notify($a, 'Editorial assignment updated', $request->user());
        });

        return back()->with('success', 'Assignment updated.');
    }

    public function acceptance(Request $request, Article $article)
    {
        abort_unless($this->workflow->canView($request->user(), $article), 403);
        $a = data_get($article->workflow?->data, 'acceptance');
        abort_unless($a, 404);

        return response(app(AcceptanceLetterPdf::class)->render($article, $a), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="acceptance-'.$article->workflow->manuscript_id.'.pdf"']);
    }

    public function adopt(Request $request, Article $article)
    {
        abort_unless($this->workflow->isAdmin($request->user()), 403);
        $request->validate(['comments' => ['required', 'string', 'max:5000']]);
        DB::transaction(function () use ($article, $request) {
            $a = Article::whereKey($article->id)->lockForUpdate()->firstOrFail();
            abort_if($a->workflow, 409);
            abort_unless(in_array($a->status->value, ['draft', 'submitted', 'under_review', 'revision_required', 'approved', 'production', 'published']), 422);
            $stage = match ($a->status->value) {
                'published' => 'published','draft' => 'draft',default => 'submitted'
            };
            $id = 'SJC-'.now()->year.'-'.str_pad($this->workflow->sequence('manuscript-'.now()->year), 4, '0', STR_PAD_LEFT);
            $w = ManuscriptWorkflow::create(['article_id' => $a->id, 'manuscript_id' => $id, 'stage' => $stage, 'data' => ['legacy_status' => $a->status->value, 'current_submission_id' => $a->submissions()->latest('round')->value('id')]]);
            if ($a->pdf_path && Storage::disk('local')->exists($a->pdf_path)) {
                WorkflowFile::create(['article_id' => $a->id, 'uploaded_by_id' => $request->user()->id, 'purpose' => 'manuscript', 'path' => $a->pdf_path, 'original_name' => basename($a->pdf_path), 'mime_type' => 'application/pdf', 'checksum' => hash('sha256', Storage::disk('local')->get($a->pdf_path)), 'size' => Storage::disk('local')->size($a->pdf_path), 'round' => 0]);
            }
            $this->workflow->log($a, $request->user(), 'legacy_adopted', $a->status->value, $stage, $request->input('comments'));
        });

        return back()->with('success', 'Existing record adopted. Published content is preserved.');
    }

    public function override(Request $request, Article $article)
    {
        abort_unless($request->user()->hasRole('super-admin'), 403);
        $v = $request->validate(['stage' => ['required', Rule::in(config('workflow.stages'))], 'comments' => ['required', 'string', 'min:20', 'max:5000']]);
        // Overrides may send work back for correction, but cannot bypass proof, DOI or publication gates.
        abort_unless(in_array($v['stage'], ['submitted', 'initial_check', 'plagiarism_check', 'editorial_screening', 'reviewer_assignment', 'editor_recheck', 'copyediting']), 422);
        DB::transaction(function () use ($article, $v, $request) {
            $a = Article::whereKey($article->id)->lockForUpdate()->firstOrFail();
            $w = $a->workflow;
            abort_unless($w && ! in_array($w->stage,['published', 'indexing', 'indexed', 'archived']), 409);
            $from = $w->stage;
            $d = $w->data ?? [];
            abort_if($v['stage'] === 'copyediting' && empty($d['acceptance']),422,'Accept the manuscript before copyediting.');
            unset($d['proof_approval'],$d['metadata']);
            $w->update(['stage' => $v['stage'], 'deadline' => null, 'data' => $d]);
            $this->workflow->log($a,$request->user(),'super_admin_override',$from,$v['stage'],$v['comments']);
        });

        return back()->with('success','Override recorded in the activity log.');
    }
}
