<?php

namespace Tests\Feature\Seo;

use App\Models\Tenant;
use App\Services\Analytics\GoogleAnalyticsConversion;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Private surfaces stay out of search engines (tenant subdomains), and the
 * server-side GA4 sign_up conversion behaves (GoogleAnalyticsConversion).
 */
class TenantAndAnalyticsSeoTest extends TestCase
{
    public function test_tenant_subdomains_are_disallowed_and_noindex(): void
    {
        $tenant = Tenant::create(['id' => 'test-seo-' . uniqid()]);
        $domain = $tenant->getTenantKey() . '.fakturalista.test';
        $tenant->domains()->create(['domain' => $domain]);

        try {
            $robots = $this->get('http://' . $domain . '/robots.txt')->assertOk()->getContent();
            $this->assertMatchesRegularExpression('~^Disallow: /$~m', $robots);
            $this->assertStringNotContainsString('Sitemap:', $robots);

            $this->get('http://' . $domain . '/admin/login')
                ->assertOk()
                ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        } finally {
            tenancy()->end();
            $tenant->delete();
        }
    }

    public function test_the_ga_client_id_is_read_from_the_ga_cookie(): void
    {
        $this->assertSame('123456789.1700000000', GoogleAnalyticsConversion::clientId('GA1.1.123456789.1700000000'));
        $this->assertMatchesRegularExpression('~^\d+\.\d+$~', GoogleAnalyticsConversion::clientId(null));
        $this->assertMatchesRegularExpression('~^\d+\.\d+$~', GoogleAnalyticsConversion::clientId('garbage'));
    }

    public function test_sign_up_is_sent_after_the_response_without_personal_data(): void
    {
        Http::fake();
        config(['seo.ga4.enabled' => true, 'seo.ga4.measurement_id' => 'G-TEST', 'seo.ga4.api_secret' => 'secret']);

        $request = Request::create('/register', 'POST', [], ['_ga' => 'GA1.1.111.222']);
        app(GoogleAnalyticsConversion::class)->signUp($request);

        Http::assertNothingSent();      // deferred until the response is sent
        $this->app->terminate();

        Http::assertSent(function (ClientRequest $sent) {
            $body = $sent->data();

            return str_starts_with($sent->url(), 'https://www.google-analytics.com/mp/collect?')
                && str_contains($sent->url(), 'measurement_id=G-TEST')
                && $body['client_id'] === '111.222'
                && $body['events'][0]['name'] === 'sign_up'
                && ! str_contains(json_encode($body), '@');
        });
    }

    public function test_no_event_is_sent_without_an_api_secret(): void
    {
        Http::fake();
        config(['seo.ga4.api_secret' => null]);

        app(GoogleAnalyticsConversion::class)->signUp(Request::create('/register', 'POST'));
        $this->app->terminate();

        Http::assertNothingSent();
    }
}
