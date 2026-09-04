<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Enums\ReviewStatus;
use App\Models\Article;
use App\Models\Author;
use App\Models\IndexingService;
use App\Models\JournalIssue;
use App\Models\JournalVolume;
use App\Models\Review;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowActivity;
use App\Models\WorkflowFile;
use App\Notifications\WorkflowNotification;
use App\Services\ArticleWorkflowService;
use App\Services\ManuscriptWorkflowService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ManuscriptWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    private User $editor;

    private User $admin;

    private User $reviewer;

    private Article $article;

    private ManuscriptWorkflowService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('local');
        $this->seed(RolePermissionSeeder::class);
        $this->author = $this->person('author');
        $this->editor = $this->person('editor');
        $this->admin = $this->person('admin');
        $this->reviewer = $this->person('reviewer');
        $this->article = Article::factory()->draft()->create(['created_by_id' => $this->author->id, 'assigned_editor_id' => $this->editor->id, 'keywords' => ['cardiology'], 'references' => ['Reference 1'], 'doi' => null, 'article_number' => null, 'published_at' => null]);
        $a = Author::factory()->create(['user_id' => $this->author->id, 'email' => $this->author->email, 'organization' => 'Test University']);
        $this->article->authors()->attach($a, ['is_corresponding' => true, 'sort_order' => 0]);
        $this->service = app(ManuscriptWorkflowService::class);
    }

    private function person(string $role): User
    {
        $u = User::factory()->create(['status' => 'active', 'is_active' => true, 'email_verified_at' => now()]);
        $u->roles()->attach(Role::where('slug', $role)->first());

        return $u;
    }

    private function pdf(string $name = 'manuscript.pdf')
    {
        return UploadedFile::fake()->create($name, 10, 'application/pdf');
    }

    private function declarations(): array
    {
        return array_fill_keys(['original', 'exclusive', 'authors_approve', 'ethics', 'conflicts'], '1');
    }

    private function act(string $action, array $data = [], ?User $actor = null)
    {
        return $this->service->execute($this->article, $actor ?? $this->editor, $action, $data);
    }

    private function submit()
    {
        return $this->act('submit', ['declarations' => $this->declarations(), 'manuscript' => $this->pdf(), 'cover_letter' => $this->pdf('cover.pdf')], $this->author);
    }

    private function reachReview(): Review
    {
        $this->submit();
        $this->act('start_check');
        $this->act('approve_check', ['checklist' => array_fill_keys(['manuscript', 'cover_letter', 'authors', 'abstract', 'keywords', 'references', 'documents', 'format'], '1')]);
        $this->act('pass_similarity', ['similarity' => 8, 'comments' => 'Report checked', 'report' => $this->pdf('similarity.pdf')]);
        $this->act('send_review');
        $this->act('assign_reviewer', ['reviewer_id' => $this->reviewer->id, 'deadline' => now()->addDays(20)->toDateString(), 'invitation_deadline' => now()->addDays(5)->toDateString(), 'editor_message' => 'Please review this manuscript.']);
        $review = $this->article->reviews()->latest('id')->firstOrFail();
        $this->actingAs($this->reviewer)->post(route('reviewer.reviews.accept', $review))->assertRedirect();
        $this->actingAs($this->reviewer)->put(route('reviewer.reviews.update', $review), ['recommendation' => 'approve', 'comments_to_author' => 'This is a thorough manuscript with clear methods and sound conclusions.', 'confidential_comments' => 'Private assessment must not be visible to author.', 'confirmation' => 1])->assertRedirect();

        return $review->refresh();
    }

    public function test_end_to_end_publication_preserves_existing_article_and_archive()
    {
        $review = $this->reachReview();
        $this->assertSame(ReviewStatus::Completed, $review->status);
        $id = $this->article->fresh()->workflow->manuscript_id;
        $this->act('accept', ['comments' => 'Accepted after independent review.']);
        $this->actingAs($this->author)->get(route('workflow.acceptance', $this->article))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->act('start_copyediting');
        $this->act('save_copyediting', ['edited_manuscript' => $this->pdf('edited.pdf')]);
        $this->act('send_proof', ['galley' => $this->pdf('galley.pdf'), 'deadline' => now()->addDays(7)->toDateString()]);
        $this->act('proof_corrections', ['comments' => 'Please correct the spelling.', 'correction' => $this->pdf('corrections.pdf')], $this->author);
        $this->act('send_proof', ['galley' => $this->pdf('final-galley.pdf'), 'deadline' => now()->addDays(7)->toDateString()]);
        $this->act('approve_proof', [], $this->author);
        $volume = JournalVolume::create(['number' => '5', 'year' => 2026]);
        $issue = JournalIssue::create(['journal_volume_id' => $volume->id, 'number' => '2']);
        $this->act('verify_metadata', ['title' => $this->article->title, 'abstract' => 'Verified abstract', 'publication_type' => 'research', 'volume' => '5', 'issue' => '2', 'year' => 2026, 'pages' => '1–8', 'publication_date' => now()->toDateString(), 'keywords' => 'cardiology, heart', 'references' => 'Reference 1', 'journal_issue_id' => $issue->id, 'final_pdf' => $this->pdf('publication.pdf')]);
        $this->act('article_number', [], $this->admin);
        $this->act('prepare_doi', [], $this->admin);
        $this->act('submit_doi', [], $this->admin);
        $doi = ['doi' => '10.1234/sjc.test', 'agency' => 'Manual test', 'evidence_url' => 'https://doi.org/10.1234/sjc.test', 'confirmed' => 1];
        $this->act('register_doi', $doi, $this->admin);
        $this->act('activate_doi', $doi, $this->admin);
        $this->act('publish', [], $this->admin);
        $this->act('indexing', [], $this->admin);
        $index = IndexingService::create(['name' => 'Example index', 'official_url' => 'https://example.org']);
        $this->act('save_indexing', ['service_id' => $index->id, 'indexing_status' => 'indexed', 'indexed_url' => 'https://example.org/article'], $this->admin);
        $this->act('indexed', [], $this->admin);
        $this->act('archive', [], $this->admin);
        $this->assertSame(ArticleStatus::Published, $this->article->fresh()->status);
        $this->assertSame($id, $this->article->fresh()->workflow->manuscript_id);
        $this->assertCount(1, $issue->articles);
        $this->assertSame('SJC-2026-V05-I02-A001', $this->article->fresh()->article_number);
        $this->get(route('articles.show', $this->article))->assertOk();
        $this->assertGreaterThan(15, WorkflowActivity::count());
    }

    public function test_skipped_checks_and_legacy_publication_bypass_are_blocked()
    {
        $this->submit();
        $this->actingAs($this->editor)->post(route('workflow.action', $this->article), ['action' => 'accept', 'comments' => 'Cannot skip checks'])->assertSessionHasErrors('workflow');
        $this->expectException(\DomainException::class);
        app(ArticleWorkflowService::class)->transition($this->article, ArticleStatus::Published, $this->admin);
    }

    public function test_private_reports_confidential_reviews_and_other_manuscripts_are_protected()
    {
        $this->reachReview();
        $report = WorkflowFile::where('purpose', 'report')->firstOrFail();
        $other = $this->person('author');
        $otherEditor = $this->person('editor');
        $this->actingAs($other)->get(route('workflow.show', $this->article))->assertForbidden();
        $this->actingAs($otherEditor)->get(route('workflow.show', $this->article))->assertForbidden();
        $this->actingAs($this->author)->get(route('workflow.download', $report))->assertForbidden();
        $this->actingAs($this->reviewer)->get(route('workflow.download', $report))->assertForbidden();
        $this->actingAs($this->author)->get(route('workflow.show', [$this->article, 'tab' => 'reviews']))->assertOk()->assertDontSee('Private assessment');
        $this->actingAs($this->author)->post(route('workflow.action', $this->article), ['action' => 'accept', 'comments' => 'No'])->assertForbidden();
    }

    public function test_revision_preserves_files_and_requires_response_document()
    {
        $this->reachReview();
        $original = WorkflowFile::where('purpose', 'manuscript')->firstOrFail();
        $id = $this->article->fresh()->workflow->manuscript_id;
        $this->act('major_revision', ['comments' => 'Provide additional analysis', 'deadline' => now()->addDays(30)->toDateString()]);
        $this->actingAs($this->author)->post(route('workflow.action', $this->article), ['action' => 'revise', 'declarations' => $this->declarations(), 'manuscript' => $this->pdf('revision.pdf')])->assertSessionHasErrors('response');
        $this->act('revise', ['declarations' => $this->declarations(), 'manuscript' => $this->pdf('revision.pdf'), 'response' => $this->pdf('response.pdf')], $this->author);
        $this->assertSame($id, $this->article->fresh()->workflow->manuscript_id);
        $this->assertSame('revision_submitted', $this->article->fresh()->workflow->stage);
        $this->assertSame(2, WorkflowFile::where('purpose', 'manuscript')->count());
        Storage::disk('local')->assertExists($original->path);
    }

    public function test_submission_requires_declarations_and_files()
    {
        $this->actingAs($this->author)->post(route('workflow.action', $this->article), ['action' => 'submit'])->assertSessionHasErrors('declarations.original');
        $this->assertDatabaseCount('manuscript_workflows', 0);
    }

    public function test_workflow_pages_render_for_each_participant()
    {
        $this->submit();
        foreach ([$this->author, $this->admin, $this->editor] as $user) {
            $this->actingAs($user)->get(route('workflow.index'))->assertOk();
            foreach (($user->id === $this->author->id ? ['overview', 'files', 'reviews', 'revisions', 'proofs', 'publication', 'activity'] : ['overview', 'files', 'reviews', 'revisions', 'proofs', 'publication', 'activity', 'editorial', 'plagiarism', 'reviewers', 'acceptance', 'copyediting', 'metadata', 'doi', 'indexing']) as $tab) {
                $this->get(route('workflow.show', [$this->article, 'tab' => $tab]))->assertOk();
            }
        }
    }

    public function test_multiple_supplementary_files_and_filtered_reports_are_scoped()
    {
        $this->act('submit', ['declarations' => $this->declarations(), 'manuscript' => $this->pdf(), 'cover_letter' => $this->pdf('cover.pdf'), 'supplementary' => [$this->pdf('data.pdf'), $this->pdf('methods.pdf')]], $this->author);
        $this->assertSame(2, WorkflowFile::where('purpose', 'supplementary')->count());
        $this->actingAs($this->author)->get(route('workflow.index', ['export' => 'csv']))->assertOk()->assertDownload('manuscript-report.csv');
        $this->get(route('workflow.show', [$this->article, 'tab' => 'plagiarism']))->assertForbidden();
        $this->get(route('author.dashboard'))->assertOk()->assertSee('Under check');
    }

    public function test_completed_reviews_are_locked_and_reopened_with_an_audit_snapshot()
    {
        $review = $this->reachReview();
        $payload = ['recommendation' => 'reject', 'comments_to_author' => 'Changed recommendation without reopening should be blocked.', 'confirmation' => 1];
        $this->actingAs($this->reviewer)->put(route('reviewer.reviews.update', $review), $payload)->assertForbidden();
        $this->actingAs($this->editor)->post(route('workflow.review.reopen', $review), ['comments' => 'Please assess the clarification.', 'deadline' => now()->addDays(7)->toDateString()])->assertRedirect();
        $this->assertSame(ReviewStatus::InProgress, $review->fresh()->status);
        $activity = WorkflowActivity::where('action', 'review_reopened')->firstOrFail();
        $this->assertSame('approve', data_get($activity->data, 'previous_report.recommendation'));
        $this->assertFalse($activity->author_visible);
    }

    public function test_deadline_reminders_are_idempotent_and_settings_require_super_admin()
    {
        $review = $this->reachReview();
        $this->act('major_revision', ['comments' => 'Revise methods', 'deadline' => now()->addDay()->toDateString()]);
        Notification::fake();
        $this->artisan('workflow:remind')->assertSuccessful();
        $this->artisan('workflow:remind')->assertSuccessful();
        Notification::assertSentToTimes($this->author, WorkflowNotification::class, 1);
        $this->assertDatabaseCount('workflow_reminders', 1);
        $this->actingAs($this->admin)->get(route('workflow.settings'))->assertForbidden();
        $this->actingAs($this->person('super-admin'))->get(route('workflow.settings'))->assertOk();
    }

    public function test_api_submission_cannot_skip_declarations()
    {
        Sanctum::actingAs($this->author,['*']);
        $this->postJson(route('api.author.articles.submit', $this->article))->assertUnprocessable();
        $this->assertDatabaseCount('manuscript_workflows',0);
    }
    public function test_admin_assigns_editor_then_reviewer_after_author_submission(): void
    {
        $this->article->update(['assigned_editor_id' => null]);
        $this->submit();
        Notification::assertSentTo($this->admin, \App\Notifications\ArticleSubmittedNotification::class);
        $this->actingAs($this->admin)->get(route('workflow.index'))->assertOk()->assertSee($this->article->title);
        $this->actingAs($this->editor)->get(route('workflow.show', $this->article))->assertForbidden();
        $this->actingAs($this->author)->post(route('workflow.assignment', $this->article), ['editor_id' => $this->editor->id, 'comments' => 'Attempted self assignment'])->assertForbidden();
        $this->actingAs($this->admin)->post(route('workflow.assignment', $this->article), ['editor_id' => $this->editor->id, 'comments' => 'Assign the handling editor'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($this->editor->id, $this->article->fresh()->assigned_editor_id);
        Notification::assertSentTo($this->editor, WorkflowNotification::class);
        $this->actingAs($this->editor)->get(route('workflow.show', $this->article))->assertOk();
        $invite = ['action' => 'assign_reviewer', 'reviewer_id' => $this->reviewer->id, 'deadline' => now()->addDays(21)->toDateString(), 'invitation_deadline' => now()->addDays(7)->toDateString(), 'editor_message' => 'Please assess the submitted manuscript.'];
        $this->actingAs($this->admin)->post(route('workflow.action', $this->article), $invite)->assertSessionHasErrors('workflow');
        $this->act('start_check');
        $this->act('approve_check', ['checklist' => array_fill_keys(['manuscript', 'cover_letter', 'authors', 'abstract', 'keywords', 'references', 'documents', 'format'], '1')]);
        $this->act('pass_similarity', ['similarity' => 5, 'comments' => 'Similarity assessment passed', 'report' => $this->pdf('report.pdf')]);
        $this->act('send_review');
        $this->actingAs($this->admin)->post(route('workflow.action', $this->article), $invite)->assertRedirect()->assertSessionHasNoErrors();
        $review = $this->article->reviews()->firstOrFail();
        $this->assertSame($this->admin->id, $review->assigned_by_id);
        Notification::assertSentTo($this->reviewer, \App\Notifications\ReviewAssignedNotification::class);
        $this->actingAs($this->reviewer)->get(route('reviewer.reviews.show', $review))->assertOk();
        $this->post(route('reviewer.reviews.accept', $review))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('under_review', $this->article->fresh()->workflow->stage);
    }
    public function test_storage_outage_rolls_back_workflow_stage_and_file_records(): void
    {
        $this->article->workflow()->create(['stage' => 'plagiarism_check']);
        $root = tempnam(sys_get_temp_dir(), 'failed-storage-');
        config(['filesystems.disks.local.root' => $root]);
        Storage::forgetDisk('local');
        try {
            $this->service->execute($this->article, $this->editor, 'pass_similarity', [
                'similarity' => 5, 'comments' => 'Similarity is within the accepted threshold.',
                'report' => UploadedFile::fake()->createWithContent('paper.pdf', "%PDF-1.4\npaper"),
            ]);
            $this->fail('A failed upload must not complete the workflow action.');
        } catch (\League\Flysystem\FilesystemException $exception) {
            $this->assertSame('plagiarism_check', $this->article->workflow()->first()->stage);
            $this->assertSame(0, WorkflowFile::where('article_id', $this->article->id)->count());
            $this->assertSame(0, WorkflowActivity::where('article_id', $this->article->id)->count());
        } finally {
            unlink($root);
            Storage::forgetDisk('local');
        }
    }
}
