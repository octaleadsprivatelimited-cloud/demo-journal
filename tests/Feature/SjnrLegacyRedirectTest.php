<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SjnrLegacyRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_reviewed_legacy_article_and_encoded_pdf_paths_redirect_on_the_new_site(): void
    {
        config(['journal.profile' => 'sjnr', 'publication.indexing_enabled' => false]);
        Setting::put('import.sjnr.legacy_routes', [
            '/journals/42/nursing-research/abstract' => '/article/nursing-research',
            '/storage/live/Original Nursing Paper.pdf' => '/article/nursing-research/pdf',
            '/storage/topeditors/Chief Editor.jpg' => '/storage/imports/sjnr/editorial/chief.jpg',
            '/storage/editor/Editor Portrait.png' => '/storage/imports/sjnr/editorial/editor.png',
            '/storage/index/Published Logo.png' => '/storage/imports/sjnr/pages/logo.png',
        ], 'imports', false);

        $this->get('/journals/42/nursing-research/abstract')->assertStatus(301)
            ->assertRedirect('/article/nursing-research')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/storage/live/Original%20Nursing%20Paper.pdf')->assertStatus(301)
            ->assertRedirect('/article/nursing-research/pdf');
        $this->get('/storage/topeditors/Chief%20Editor.jpg')->assertStatus(301)
            ->assertRedirect('/storage/imports/sjnr/editorial/chief.jpg');
        $this->get('/storage/editor/Editor%20Portrait.png')->assertStatus(301)
            ->assertRedirect('/storage/imports/sjnr/editorial/editor.png');
        $this->get('/storage/index/Published%20Logo.png')->assertStatus(301)
            ->assertRedirect('/storage/imports/sjnr/pages/logo.png');
        $this->get('/unknown-legacy-page')->assertNotFound();
        $this->get('/storage/secret-manuscript.pdf')->assertForbidden();
    }

    public function test_redirects_refuse_external_destinations_loops_and_the_sjc_profile(): void
    {
        Setting::put('import.sjnr.legacy_routes', ['/old' => '/article/nursing-research'], 'imports', false);
        $this->get('/old')->assertNotFound();

        config(['journal.profile' => 'sjnr']);
        foreach (['https://elsewhere.test/', '//elsewhere.test/', '/old', "/bad\r\nLocation: https://elsewhere.test/"] as $destination) {
            Setting::put('import.sjnr.legacy_routes', ['/old' => $destination], 'imports', false);
            $this->get('/old')->assertNotFound();
        }
    }
}
