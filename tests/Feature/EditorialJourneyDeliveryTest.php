<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\JournalIssue;
use App\Models\JournalVolume;
use App\Models\Review;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

final class EditorialJourneyDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private ArrayTransport $transport;

    private User $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
        Storage::fake('public');
        config([
            'queue.default' => 'database',
            'queue.connections.database.connection' => 'sqlite',
            'queue.failed.database' => 'sqlite',
            'mail.default' => 'array',
            'publication.contact_email' => 'office@example.test',
            'publication.account_notification_email' => 'office@example.test',
            'publication.features.author_registration' => true,
            'security.local_admin_bypass.enabled' => false,
            'security.local_author_bypass.enabled' => false,
            'security.local_editor_bypass.enabled' => false,
            'security.local_reviewer_bypass.enabled' => false,
        ]);
        $this->transport = Mail::mailer()->getSymfonyTransport();
        $this->super = $this->staff('super-admin', 'super@example.test');
    }

    public function test_registration_submission_revision_proof_and_publication_deliver_actionable_emails_through_the_database_queue(): void
    {
        // No notification or queue fake: jobs serialize, run and render complete emails.
        $author = $this->applyAndApprove('author');
        $editor = $this->applyAndApprove('editor');
        $reviewer = $this->applyAndApprove('reviewer');
        $this->transport->flush();

        $this->as($author)->post(route('author.articles.store'), [
            'intent' => 'submit', 'title' => 'End to end cardiology manuscript',
            'abstract' => 'The full study abstract, including the methods and main findings.',
            'publication_type' => 'research', 'keywords' => 'cardiology, clinical study',
            'references' => 'Reference one', 'corresponding_index' => 0,
            'author_details' => [['name' => $author->name, 'email' => $author->email, 'organization' => 'Test University']],
            'declarations' => $this->declarations(),
            'manuscript' => $this->pdf('manuscript.pdf'), 'cover_letter' => $this->pdf('cover.pdf'),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $article = Article::where('created_by_id', $author->id)->firstOrFail();
        $this->assertSame('submitted', $article->workflow->stage);
        $this->assertCount(0, $this->transport->messages());
        $this->deliver();
        $receipt = $this->email($author, 'Submission received:');
        $this->assertStringContainsString($article->workflow->manuscript_id, $receipt->getHtmlBody());
        $this->assertStringContainsString($article->title, $receipt->getHtmlBody());
        $this->assertCount(3, $this->transport->messages()); // Author, Super Admin and office, once each.
        $this->as($author)->get($this->link($receipt, '/author/articles/'))->assertRedirect(route('workflow.show', $article));
        $alert = $this->email($this->super, 'New article submission:');
        $this->as($this->super)->get($this->link($alert, '/admin/submissions/'))->assertOk()->assertSee($article->title);
        $this->as($editor)->get(route('workflow.show', $article))->assertForbidden();
        $this->get(route('articles.show', $article))->assertNotFound();

        $this->as($this->super)->get(route('admin.dashboard'))->assertOk()->assertSee(route('workflow.index', ['unassigned' => 1]), false);
        $this->get(route('workflow.index', ['unassigned' => 1]))->assertOk()->assertSee($article->title);
        $this->post(route('workflow.assignment', $article), ['editor_id' => $editor->id, 'comments' => 'Assign the handling editor.'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->deliver();
        $assigned = $this->email($editor, 'Editorial assignment updated');
        $this->as($editor)->get($this->link($assigned, '/workflow/'))->assertOk()->assertSee($article->title);
        $this->get(route('editor.dashboard'))->assertOk()->assertSee($article->title);
        $this->as($this->super)->get(route('workflow.index', ['unassigned' => 1]))->assertOk()->assertViewHas('articles', fn ($articles) => $articles->isEmpty());

        $this->act($article, $editor, 'start_check');
        $this->act($article, $editor, 'approve_check', ['checklist' => array_fill_keys(['manuscript', 'cover_letter', 'authors', 'abstract', 'keywords', 'references', 'documents', 'format'], '1')]);
        $this->act($article, $editor, 'pass_similarity', ['similarity' => 6, 'comments' => 'Similarity checked.', 'report' => $this->pdf('similarity.pdf')]);
        $this->act($article, $editor, 'send_review');
        $this->act($article, $editor, 'assign_reviewer', ['reviewer_id' => $reviewer->id, 'deadline' => now()->addDays(20)->toDateString(), 'invitation_deadline' => now()->addDays(5)->toDateString(), 'editor_message' => 'Please assess the methods and clinical relevance.']);
        $review = $article->reviews()->firstOrFail();
        $this->deliver();
        $invitation = $this->email($reviewer, 'Review assignment:');
        $this->assertStringContainsString('Respond to this invitation by:', $invitation->getHtmlBody());
        $this->assertStringContainsString('Please assess the methods', $invitation->getHtmlBody());
        $this->as($reviewer)->get($this->link($invitation, '/reviewer/reviews/'))->assertOk();
        $this->post(route('reviewer.reviews.accept', $review))->assertRedirect()->assertSessionHasNoErrors();
        $this->put(route('reviewer.reviews.update', $review), [
            'recommendation' => 'major_revision', 'comments_to_author' => 'Please clarify the study methods and the primary outcome analysis.',
            'confidential_comments' => 'CONFIDENTIAL-EDITOR-ONLY-REPORT', 'confirmation' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->deliver();
        $reviewUpdate = $this->email($author, 'Review update:');
        $this->assertStringNotContainsString('CONFIDENTIAL-EDITOR-ONLY-REPORT', $reviewUpdate->getHtmlBody());
        $this->as($author)->get(route('workflow.show', [$article, 'tab' => 'reviews']))->assertOk()
            ->assertSee('Please clarify the study methods')->assertDontSee('CONFIDENTIAL-EDITOR-ONLY-REPORT');
        $this->as($editor)->get($this->link($this->email($editor, 'Review completed:'), '/admin/reviews/'))->assertOk();

        $this->act($article, $editor, 'major_revision', ['comments' => 'Clarify the methods and provide a point by point response.', 'deadline' => now()->addDays(30)->toDateString()]);
        $this->deliver();
        $revision = $this->email($author, 'Major revision requested');
        $this->assertStringContainsString($article->title, $revision->getHtmlBody());
        $this->assertStringContainsString('Action due:', $revision->getHtmlBody());
        $this->as($author)->get($this->link($revision, '/workflow/'))->assertOk()->assertSee('Clarify the methods');
        $this->transport->flush();
        $this->act($article, $author, 'revise', ['declarations' => $this->declarations(), 'manuscript' => $this->pdf('revision.pdf'), 'response' => $this->pdf('response.pdf')]);
        $this->deliver();
        $this->assertSame(2, $article->submissions()->count());
        $this->assertCount(4, $this->transport->messages()); // Author, assigned editor, Super Admin and office.
        $this->assertStringContainsString('Submission round: 2', $this->email($author, 'Submission received:')->getHtmlBody());
        $this->act($article, $editor, 'editor_recheck');
        $this->act($article, $editor, 'accept', ['comments' => 'Accepted after review and revision.']);
        $this->deliver();
        $acceptance = $this->email($author, 'Manuscript accepted');
        $this->as($author)->get($this->link($acceptance, '/workflow/'))->assertOk();
        $this->get(route('workflow.acceptance', $article))->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->act($article, $editor, 'start_copyediting');
        $this->act($article, $editor, 'save_copyediting', ['edited_manuscript' => $this->pdf('edited.pdf')]);
        $this->act($article, $editor, 'send_proof', ['galley' => $this->pdf('proof.pdf'), 'deadline' => now()->addDays(7)->toDateString()]);
        $this->deliver();
        $proof = $this->email($author, 'Galley proof ready for approval');
        $this->as($author)->get($this->link($proof, '/workflow/'))->assertOk()->assertSee('Approve final proof');
        $this->act($article, $author, 'approve_proof');
        $volume = JournalVolume::create(['number' => '5', 'year' => now()->year]);
        $issue = JournalIssue::create(['journal_volume_id' => $volume->id, 'number' => '2']);
        $this->act($article, $editor, 'verify_metadata', ['title' => $article->title, 'abstract' => 'Verified abstract', 'publication_type' => 'research', 'volume' => '5', 'issue' => '2', 'year' => now()->year, 'pages' => '1–8', 'publication_date' => today()->toDateString(), 'keywords' => 'cardiology, clinical study', 'references' => 'Reference one', 'journal_issue_id' => $issue->id, 'final_pdf' => $this->pdf('publication.pdf')]);
        foreach (['article_number', 'prepare_doi', 'submit_doi'] as $action) {
            $this->act($article, $this->super, $action);
        }
        $doi = ['doi' => '10.1234/isolated-workflow-test', 'agency' => 'Isolated test evidence', 'evidence_url' => 'https://example.test/doi-evidence', 'confirmed' => '1'];
        $this->act($article, $this->super, 'register_doi', $doi);
        $this->act($article, $this->super, 'activate_doi', $doi);
        $this->deliver();
        $this->transport->flush();
        $this->act($article, $this->super, 'publish');
        $this->deliver();
        $published = $this->email($author, 'Article Published:');
        $authorMail = $this->transport->messages()->filter(fn ($sent) => $sent->getOriginalMessage()->getTo()[0]->getAddress() === $author->email);
        $this->assertCount(1, $authorMail);
        $this->as($author)->get($this->link($published, '/author/articles/'))->assertRedirect(route('workflow.show', $article));
        $this->get(route('workflow.show', $article))->assertOk()->assertSee('View published article');
        $this->get(route('articles.show', $article))->assertOk()->assertSee($article->title);
        $this->get(route('workflow.index'))->assertOk()->assertSee(route('author.articles.show', $article), false);
        $this->assertSame(ArticleStatus::Published, $article->fresh()->status);
    }

    public function test_editor_dashboard_counts_only_assigned_manuscripts(): void
    {
        $editor = $this->staff('editor', 'editor-scope@example.test');
        foreach ([ArticleStatus::Approved, ArticleStatus::Scheduled] as $status) {
            Article::factory()->create(['assigned_editor_id' => $editor->id, 'status' => $status]);
            Article::factory()->count(2)->create(['assigned_editor_id' => null, 'status' => $status]);
        }
        $this->as($editor)->get(route('editor.dashboard'))->assertOk()->assertViewHas('stats', fn ($stats) => $stats['approved'] === 1 && $stats['scheduled'] === 1);
    }

    public function test_stage_filter_includes_drafts_without_a_workflow_and_preserves_author_scope(): void
    {
        $author = $this->staff('author', 'draft-filter@example.test');
        $draft = Article::factory()->draft()->create(['created_by_id' => $author->id]);
        Article::factory()->draft()->create();
        $submitted = Article::factory()->create(['created_by_id' => $author->id, 'status' => ArticleStatus::Submitted]);
        $submitted->workflow()->create(['stage' => 'initial_check']);
        $this->as($author)->get(route('workflow.index', ['stage' => 'draft']))->assertOk()
            ->assertViewHas('articles', fn ($articles) => $articles->modelKeys() === [$draft->id]);
        $this->get(route('workflow.index', ['stage' => 'initial_check']))->assertOk()
            ->assertViewHas('articles', fn ($articles) => $articles->modelKeys() === [$submitted->id]);
    }

    public function test_rejection_sends_one_author_decision_and_failed_submission_sends_no_receipt(): void
    {
        $author = $this->staff('author', 'rejected-author@example.test');
        $editor = $this->staff('editor', 'rejecting-editor@example.test');
        $article = Article::factory()->create(['created_by_id' => $author->id, 'assigned_editor_id' => $editor->id, 'status' => ArticleStatus::Submitted]);
        $article->workflow()->create(['stage' => 'initial_check', 'manuscript_id' => 'SJC-REJECTION-TEST']);
        $this->act($article, $editor, 'reject', ['comments' => 'The manuscript does not meet the journal scope.']);
        $this->deliver();
        $decision = $this->email($author, 'Manuscript rejected');
        $this->as($author)->get($this->link($decision, '/workflow/'))->assertOk()->assertSee('Rejected');
        $this->get(route('articles.show', $article))->assertNotFound();
        $this->transport->flush();
        $count = Article::count();
        $this->post(route('author.articles.store'), ['intent' => 'submit', 'title' => 'Incomplete submission', 'publication_type' => 'research'])
            ->assertSessionHasErrors('declarations.original');
        $this->assertSame($count, Article::count());
        $this->assertDatabaseCount('jobs', 0);
        $this->assertCount(0, $this->transport->messages());
    }

    public function test_reviewer_deadline_reminder_opens_the_assigned_review_and_is_not_sent_twice(): void
    {
        $reviewer = $this->staff('reviewer', 'review-reminder@example.test');
        $article = Article::factory()->create(['assigned_editor_id' => null, 'status' => ArticleStatus::UnderReview]);
        $submission = $article->submissions()->create(['submitted_by_id' => $article->created_by_id, 'round' => 1, 'status' => 'in_review', 'submitted_at' => now()]);
        $article->workflow()->create(['stage' => 'under_review', 'manuscript_id' => 'SJC-REMINDER-TEST', 'data' => ['current_submission_id' => $submission->id]]);
        $review = Review::factory()->create(['article_id' => $article->id, 'submission_id' => $submission->id, 'reviewer_id' => $reviewer->id, 'assigned_by_id' => $this->super->id, 'status' => 'in_progress', 'due_at' => now()->addDay()]);
        $this->artisan('workflow:remind')->assertSuccessful();
        $this->artisan('workflow:remind')->assertSuccessful();
        $this->deliver();
        $reminder = $this->email($reviewer, 'Review due soon');
        $this->assertCount(1, $this->transport->messages());
        $this->assertStringContainsString($review->due_at->toFormattedDateString(), $reminder->getHtmlBody());
        $this->as($reviewer)->get($this->link($reminder, '/reviewer/reviews/'))->assertOk();
    }

    private function applyAndApprove(string $role): User
    {
        if (auth()->check()) {
            $this->post(route('logout'))->assertRedirect();
        }
        $this->post(route($role.'.register.store'), ['name' => ucfirst($role).' Journey', 'email' => $role.'-journey@example.test', 'organization' => 'Test University', 'password' => 'Strong!Portal123', 'password_confirmation' => 'Strong!Portal123', 'terms' => '1'])
            ->assertRedirect(route('registration.submitted'))->assertSessionHasNoErrors();
        $user = User::where('email', $role.'-journey@example.test')->firstOrFail();
        $this->deliver();
        $verification = $this->email($user, 'Verify your email address');
        $this->get($this->link($verification, '/registration/verify-email/'))->assertOk()->assertSee('Email address verified');
        $this->assertFalse($user->fresh()->isActive());
        $this->post(route($role.'.login.store'), ['email' => $user->email, 'password' => 'Strong!Portal123'])->assertSessionHasErrors('email');
        $this->as($this->super)->post(route('admin.users.approve', $user))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($user->fresh()->isActive(), 'Super Admin approval must activate the applicant.');
        $this->deliver();
        $approved = $this->email($user, 'account application approved');
        $this->assertStringContainsString(route($role.'.login'), $approved->getHtmlBody());
        $this->post(route('logout'));
        $this->post(route($role.'.login.store'), ['email' => $user->email, 'password' => 'Strong!Portal123'])->assertRedirect(route($role.'.dashboard'))->assertSessionHasNoErrors();
        $this->get(route($role.'.dashboard'))->assertOk();

        return $user->fresh();
    }

    private function as(User $user): static
    {
        // Each role represents a separate browser session, including its password hash.
        $this->flushSession();
        $this->actingAs($user);

        return $this;
    }

    private function staff(string $role, string $email): User
    {
        $user = User::factory()->create(['email' => $email]);
        $user->roles()->attach(Role::where('slug', $role)->firstOrFail());

        return $user;
    }

    private function act(Article $article, User $actor, string $action, array $data = []): void
    {
        $this->as($actor)->post(route('workflow.action', $article), ['action' => $action] + $data)
            ->assertRedirect(route('workflow.show', $article))->assertSessionHasNoErrors();
    }

    private function declarations(): array
    {
        return array_fill_keys(['original', 'exclusive', 'authors_approve', 'ethics', 'conflicts'], '1');
    }

    private function pdf(string $name): UploadedFile
    {
        return UploadedFile::fake()->create($name, 10, 'application/pdf');
    }

    private function deliver(): void
    {
        $this->assertGreaterThan(0, DB::table('jobs')->count());
        $this->assertSame(0, Artisan::call('queue:work', ['connection' => 'database', '--queue' => 'default,mail', '--stop-when-empty' => true, '--sleep' => 0, '--tries' => 1, '--max-time' => 20]));
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
    }

    private function email(User $user, string $subject): Email
    {
        $matches = $this->transport->messages()->map(fn ($sent) => $sent->getOriginalMessage())
            ->filter(fn (Email $email) => $email->getTo()[0]->getAddress() === $user->email && str_contains($email->getSubject(), $subject));
        $this->assertCount(1, $matches, 'Expected one email for '.$user->email.' containing '.$subject);

        return $matches->first();
    }

    private function link(Email $email, string $contains): string
    {
        preg_match_all('/href="([^"]+)"/', $email->getHtmlBody(), $matches);
        $links = array_values(array_unique(array_filter(array_map(fn ($url) => html_entity_decode($url, ENT_QUOTES | ENT_HTML5), $matches[1]), fn ($url) => str_contains($url, $contains))));
        $this->assertCount(1, $links, 'Expected a working action link in '.$email->getSubject());

        return $links[0];
    }
}
