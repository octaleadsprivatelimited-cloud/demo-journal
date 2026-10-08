<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PublicAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function tracking(): void
    {
        $this->app->instance('env', 'production');
        config()->set('publication.integrations.tracking_enabled', true);
        config()->set('publication.integrations.tracking_host', 'localhost');
        config()->set('publication.integrations.analytics_id', 'G-TEST123456');
        config()->set('publication.integrations.clarity_id', null);
    }

    public function test_ga4_is_consent_gated_on_public_pages_with_matching_csp(): void
    {
        $this->tracking();
        $response = $this->get('/')->assertOk()
            ->assertSee('journal-analytics-config', false)->assertSee('G-TEST123456')
            ->assertSee('Accept analytics')->assertSee('Necessary only')
            ->assertDontSee('Microsoft Clarity records');
        $this->assertStringContainsString('https://www.googletagmanager.com', $response->headers->get('Content-Security-Policy'));
        $this->assertStringNotContainsString('https://*.clarity.ms', $response->headers->get('Content-Security-Policy'));
        $response->assertDontSee('<script async src="https://www.googletagmanager.com', false);
    }

    public function test_an_upgrade_only_policy_does_not_accidentally_block_existing_inline_scripts(): void
    {
        $this->tracking();
        config()->set('security.content_security_policy', 'upgrade-insecure-requests');
        $this->get('/')->assertOk()->assertHeader('Content-Security-Policy', 'upgrade-insecure-requests');
    }

    public function test_private_forms_queries_authenticated_users_and_other_hosts_are_excluded(): void
    {
        $this->tracking();
        foreach (['/login', '/register', '/author/register', '/contact', '/search?q=heart', '/?email=private@example.com', '/?token=secret', '/?utm_source=private@example.com'] as $path) {
            $response = $this->get($path);
            $response->assertDontSee('journal-analytics-config', false);
            $this->assertStringNotContainsString('https://www.googletagmanager.com', $response->headers->get('Content-Security-Policy'));
        }
        $this->get('http://another.example/')->assertDontSee('journal-analytics-config', false);
        $this->actingAs(User::factory()->create())->get('/')->assertOk()->assertDontSee('journal-analytics-config', false);
    }

    public function test_safe_campaigns_keep_a_clean_page_location_and_invalid_ids_never_render(): void
    {
        $this->tracking();
        $response = $this->get('/?utm_source=newsletter&utm_campaign=october')->assertOk()->assertSee('journal-analytics-config', false);
        preg_match('/<script id="journal-analytics-config" type="application\/json">(.*?)<\/script>/s', $response->getContent(), $matches);
        $tracking = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
        $this->assertNull(parse_url($tracking['page'], PHP_URL_QUERY));
        $this->assertSame('localhost', parse_url($tracking['page'], PHP_URL_HOST));
        $this->assertSame('newsletter', $tracking['campaign']['campaign_source']);
        config()->set('publication.integrations.analytics_id', 'G-TEST</script>');
        $this->get('/')->assertDontSee('journal-analytics-config', false);
    }

    public function test_tracking_is_disabled_in_local_environments_and_by_the_off_switch(): void
    {
        $this->tracking();
        $this->app->instance('env', 'local');
        $this->get('/')->assertDontSee('journal-analytics-config', false);
        $this->app->instance('env', 'production');
        config()->set('publication.integrations.tracking_enabled', false);
        $this->get('/')->assertDontSee('journal-analytics-config', false);
    }
}
