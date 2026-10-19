<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\EditorialMember;
use App\Models\JournalIssue;
use App\Models\JournalVolume;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicLayoutContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_profiles_are_unique_normalized_and_in_the_same_order_on_both_pages(): void
    {
        foreach ([['Dr . Tushar Tuliani', 2], ['Dr. Tushar Tuliani', 3], ['Dr. First Editor', 1]] as [$name, $order]) {
            EditorialMember::create(['name' => $name, 'sort_order' => $order, 'group' => 'editorial_board', 'role' => 'Editor', 'institution' => 'Example , University', 'is_active' => true]);
        }
        $this->assertSame('Dr. Tahera Nazrin', (new EditorialMember(['name' => 'Dr . Tahera Nazrin Nazrin']))->name);
        EditorialMember::create(['name' => 'Private editor', 'group' => 'editorial_board', 'role' => 'Editor', 'is_active' => false]);

        foreach (['/', '/editorial-board'] as $path) {
            $response = $this->get($path)->assertOk()->assertDontSee('Dr .')->assertDontSee('Private editor');
            $xpath = $this->xpath($response->getContent());
            $names = $xpath->query('//article[contains(concat(" ", normalize-space(@class), " "), " editor-card ")]//h3');
            $this->assertSame(['Dr. First Editor', 'Dr. Tushar Tuliani'], array_map(fn ($node) => $node->textContent, iterator_to_array($names)));
            $this->assertSame(2, $xpath->query('//img[contains(@class,"editor-portrait") and @data-image-fallback]')->length);
        }
    }

    public function test_archive_shows_empty_years_without_fabricating_issues_and_filters_articles_by_year(): void
    {
        Article::factory()->published()->create(['title' => 'New publication', 'published_at' => '2025-02-18']);
        Article::factory()->published()->create(['title' => 'Earlier publication', 'published_at' => '2022-06-30']);
        Article::factory()->draft()->create(['title' => 'Private archive draft', 'published_at' => '2023-03-01']);

        $this->get('/archive')->assertOk()->assertSee('No published issues or articles are available for 2023.')
            ->assertSee('Issue information is not available for 2025.')->assertDontSee('Private archive draft');
        $this->get('/archive?year=2025')->assertOk()->assertSee('New publication')->assertDontSee('Earlier publication');
        $this->get('/archive?year=2023')->assertOk()->assertSee('No published issues or articles are available for 2023.')->assertDontSee('New publication');
    }

    public function test_archive_links_to_later_published_issues_and_keeps_future_issues_private(): void
    {
        $volume = JournalVolume::create(['number' => '6', 'year' => 2025]);
        $issue = JournalIssue::create(['journal_volume_id' => $volume->id, 'number' => '3', 'publication_date' => '2025-05-01']);
        $future = JournalIssue::create(['journal_volume_id' => $volume->id, 'number' => '9', 'publication_date' => now()->addYear()]);
        $article = Article::factory()->published()->create(['journal_issue_id' => $issue->id, 'title' => 'Issue three article', 'published_at' => '2025-05-01']);
        Article::factory()->draft()->create(['journal_issue_id' => $issue->id, 'title' => 'Unpublished issue draft']);

        $this->get('/archive')->assertOk()->assertSee(route('archive.issue', $issue), false)->assertSee('Issue 3')->assertDontSee('Issue 9');
        $this->get(route('archive.issue', $issue))->assertOk()->assertSee($article->title)->assertDontSee('Unpublished issue draft');
        $this->get(route('archive.issue', $future))->assertNotFound();
    }

    public function test_article_actions_have_real_destinations_even_when_the_abstract_is_unavailable(): void
    {
        $article = Article::factory()->published()->create(['abstract' => null]);
        $this->get('/')->assertOk()->assertSee(route('articles.show', $article->slug).'#abstract', false)
            ->assertSee(route('articles.show', $article->slug).'#full-paper', false);
        $this->get(route('articles.show', $article->slug))->assertOk()
            ->assertSee('id="abstract"', false)->assertSee('id="full-paper"', false)
            ->assertSee('A separate abstract is not available for this article.');
    }

    public function test_footer_is_shared_and_each_destination_is_listed_once(): void
    {
        foreach (['/', '/archive', '/editorial-board', '/about', '/policies/author-guidelines'] as $path) {
            $xpath = $this->xpath($this->get($path)->assertOk()->getContent());
            $this->assertSame(1, $xpath->query('//footer')->length);
            $destinations = array_map(fn ($node) => $node->getAttribute('href'), iterator_to_array($xpath->query('//footer//a')));
            $this->assertSame($destinations, array_values(array_unique($destinations)));
        }
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        return new DOMXPath($document);
    }
}
