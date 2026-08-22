<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Author;
use App\Models\Comment;
use App\Models\ContactSubmission;
use App\Models\NewsletterSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class PublicPublicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_published_article_is_discoverable_and_a_draft_is_not(): void
    {
        $author = Author::factory()->create();
        $published = Article::factory()->published()->create([
            'title' => 'Evidence for Better Cities',
            'is_featured' => true,
        ]);
        $published->authors()->attach($author, ['is_corresponding' => true, 'sort_order' => 0]);
        $draft = Article::factory()->draft()->create(['title' => 'Private Working Draft']);

        $this->get('/')
            ->assertOk()
            ->assertSee('Evidence for Better Cities')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->get('/articles')
            ->assertOk()
            ->assertSee('Evidence for Better Cities')
            ->assertDontSee('Private Working Draft');

        $this->get('/article/'.$published->slug)
            ->assertOk()
            ->assertSee('Evidence for Better Cities');

        $this->get('/article/'.$draft->slug)->assertNotFound();
    }

    public function test_published_pdf_is_streamed_from_private_storage_and_drafts_remain_private(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $published = Article::factory()->published()->create([
            'pdf_path' => 'articles/manuscripts/published.pdf',
            'pdf_download_enabled' => true,
        ]);
        Storage::disk('local')->put($published->pdf_path, '%PDF-1.7 private publication');

        $this->get(route('articles.pdf', $published->slug))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload(str($published->title)->slug().'.pdf');

        $draft = Article::factory()->draft()->create([
            'pdf_path' => 'articles/manuscripts/draft.pdf',
            'pdf_download_enabled' => true,
        ]);
        Storage::disk('local')->put($draft->pdf_path, '%PDF-1.7 private draft');

        $this->get(route('articles.pdf', $draft->slug))->assertNotFound();
    }

    public function test_contact_and_newsletter_forms_persist_validated_data(): void
    {
        $this->post('/contact', [
            'name' => 'Ada Researcher',
            'email' => 'ada@example.test',
            'subject' => 'Permissions request',
            'category' => 'permissions',
            'message' => 'I would like permission to reproduce one figure in a classroom reader.',
            'company_website' => '',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('contact_submissions', [
            'email' => 'ada@example.test',
            'status' => 'new',
        ]);

        $this->post('/newsletter/subscribe', [
            'name' => 'Ada Researcher',
            'email' => 'ADA@example.test',
            'newsletter_website' => '',
        ])->assertRedirect()->assertSessionHas('success');

        $subscriber = NewsletterSubscriber::query()->where('email', 'ada@example.test')->firstOrFail();
        $this->get('/newsletter/unsubscribe/'.$subscriber->token)->assertRedirect('/');
        $this->assertDatabaseHas('newsletter_subscribers', [
            'id' => $subscriber->getKey(),
            'status' => 'unsubscribed',
        ]);

        $this->assertSame(1, ContactSubmission::query()->count());
    }

    public function test_honeypot_and_validation_reject_spam_without_persistence(): void
    {
        $this->from('/contact')->post('/contact', [
            'name' => 'Bot Account',
            'email' => 'bot@example.test',
            'subject' => 'Automated message',
            'category' => 'general',
            'message' => 'This is long enough to pass the ordinary message validation rule.',
            'company_website' => 'https://spam.invalid',
        ])->assertRedirect('/contact')->assertSessionHasErrors('company_website');

        $this->assertDatabaseCount('contact_submissions', 0);
    }

    public function test_comments_are_moderated_before_they_appear_publicly(): void
    {
        $article = Article::factory()->published()->create(['comments_enabled' => true]);

        $this->post('/article/'.$article->slug.'/comments', [
            'guest_name' => 'Thoughtful Reader',
            'guest_email' => 'reader@example.test',
            'body' => 'The distinction in the final section is worth developing further.',
            'comment_website' => '',
        ])->assertRedirect(route('articles.show', $article->slug).'#discussion');

        $comment = Comment::query()->firstOrFail();
        $this->assertSame('pending', $comment->status->value);

        $this->get('/article/'.$article->slug)
            ->assertOk()
            ->assertDontSee('The distinction in the final section is worth developing further.');

        $comment->forceFill(['status' => 'approved', 'approved_at' => now()])->save();

        $this->get('/article/'.$article->slug)
            ->assertOk()
            ->assertSee('The distinction in the final section is worth developing further.');
    }

    public function test_search_engine_discovery_endpoints_are_available(): void
    {
        $article = Article::factory()->published()->create();

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(route('articles.show', $article->slug), false);

        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Sitemap: '.route('sitemap'), false);
    }
}
