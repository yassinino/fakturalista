<?php

namespace Tests\Feature\Analytics;

use App\Models\AnalyticsConversion;
use App\Models\AnalyticsPageview;
use App\Services\Analytics\AnalyticsTracker;
use App\Services\Analytics\CountryResolver;
use App\Services\Analytics\TrafficSourceClassifier;
use App\Services\Analytics\UserAgentClassifier;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * First-party analytics - collection side (page payload, beacon, filters,
 * privacy, conversions). Rows written by a test are removed afterwards.
 */
class AnalyticsTrackingTest extends TestCase
{
    private const HOST = 'http://fakturalista.test';
    private const UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36';

    private int $maxView;
    private int $maxConversion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->maxView = (int) AnalyticsPageview::max('id');
        $this->maxConversion = (int) AnalyticsConversion::max('id');
    }

    protected function tearDown(): void
    {
        AnalyticsPageview::where('id', '>', $this->maxView)->delete();
        AnalyticsConversion::where('id', '>', $this->maxConversion)->delete();
        parent::tearDown();
    }

    private function newViews()
    {
        return AnalyticsPageview::where('id', '>', $this->maxView)->orderBy('id');
    }

    /** Renders a page and returns the signed analytics payload embedded in it. */
    private function payloadFrom(string $url): ?array
    {
        $html = $this->get($url)->assertOk()->getContent();
        if (! preg_match('~var data = (\{.*?\}), url = ~', $html, $m)) {
            return null;
        }

        return json_decode($m[1], true);
    }

    private function beacon(array $data, array $server = []): \Illuminate\Testing\TestResponse
    {
        return $this->call('POST', self::HOST . '/a/collect', [], [], [], array_merge([
            'HTTP_USER_AGENT' => self::UA,
            'REMOTE_ADDR'     => '41.248.0.1',
            'CONTENT_TYPE'    => 'text/plain',
        ], $server), json_encode($data));
    }

    // ── Public page tracking ────────────────────────────────────────────

    public function test_a_public_page_view_is_recorded_with_path_title_locale_country_and_device(): void
    {
        $payload = $this->payloadFrom(self::HOST . '/pricing?lang=ar&email=leak@example.com');
        $this->assertNotNull($payload, 'Public pages embed the tracker.');

        $this->beacon($payload + ['r' => 'https://www.google.com/'])->assertNoContent();

        $view = $this->newViews()->first();
        $this->assertNotNull($view);
        $this->assertSame('/pricing', $view->path, 'No query string is stored.');
        $this->assertSame('ar', $view->locale);
        $this->assertStringContainsString('Fakturalista', (string) $view->title . 'Fakturalista');
        $this->assertSame('MA', $view->country_code, '41.248.0.1 is a Moroccan address (local GeoIP database).');
        $this->assertSame('desktop', $view->device);
        $this->assertTrue($view->is_entrance);
        $this->assertSame('search', $view->source);
        $this->assertSame('google', $view->source_name);
    }

    public function test_the_beacon_cannot_be_forged_for_pages_we_did_not_render(): void
    {
        $payload = $this->payloadFrom(self::HOST . '/');

        $this->beacon(['p' => '/fake-page'] + $payload)->assertNoContent();
        $this->beacon(['t' => 'Spam title'] + $payload)->assertNoContent();
        $this->beacon(['s' => str_repeat('a', 64)] + $payload)->assertNoContent();
        $this->beacon(['ts' => time() - 86400 * 2] + $payload)->assertNoContent();
        $this->beacon(['garbage' => true])->assertNoContent();

        $this->assertSame(0, $this->newViews()->count());
    }

    public function test_the_beacon_is_rate_limited(): void
    {
        $payload = $this->payloadFrom(self::HOST . '/');

        for ($i = 0; $i < 60; $i++) {
            $this->beacon($payload);
        }
        $this->beacon($payload)->assertStatus(429);
    }

    public function test_a_reload_of_the_same_page_is_counted_once(): void
    {
        $payload = $this->payloadFrom(self::HOST . '/faq');

        $this->beacon($payload);
        $this->beacon($payload);

        $this->assertSame(1, $this->newViews()->count());
    }

    // ── Exclusions ──────────────────────────────────────────────────────

    public function test_bots_crawlers_and_monitoring_are_excluded(): void
    {
        $payload = $this->payloadFrom(self::HOST . '/');

        foreach ([
            'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/129.0 Safari/537.36',
            'Pingdom.com_bot_version_1.4_(http://www.pingdom.com/)',
            'curl/8.4.0',
            '',
        ] as $ua) {
            $this->beacon($payload, ['HTTP_USER_AGENT' => $ua]);
        }

        $this->assertSame(0, $this->newViews()->count());
    }

    public function test_do_not_track_and_global_privacy_control_are_honoured(): void
    {
        $payload = $this->payloadFrom(self::HOST . '/');

        $this->beacon($payload, ['HTTP_DNT' => '1']);
        $this->beacon($payload, ['HTTP_SEC_GPC' => '1']);

        $this->assertSame(0, $this->newViews()->count());
    }

    public function test_backoffice_and_admin_traffic_is_never_tracked(): void
    {
        // The backoffice never embeds the tracker.
        $this->assertStringNotContainsString('/a/collect', $this->get(self::HOST . '/backoffice/login')->getContent());

        // A logged-in admin browsing the public site is neither shown the tracker nor recorded.
        $payload = $this->payloadFrom(self::HOST . '/');
        $admin = \App\Models\AdminUser::create(['name' => 'T', 'email' => 'an-' . uniqid() . '@example.test', 'password' => 'x-password-1']);
        try {
            $this->actingAs($admin, 'admin');
            $this->assertStringNotContainsString('/a/collect', $this->get(self::HOST . '/')->getContent());
            $this->beacon($payload);
            $this->assertSame(0, $this->newViews()->count());
        } finally {
            $admin->delete();
        }
    }

    // ── Privacy ─────────────────────────────────────────────────────────

    public function test_no_ip_address_or_identifier_cookie_is_stored(): void
    {
        $payload = $this->payloadFrom(self::HOST . '/');
        $response = $this->beacon($payload);

        $row = (array) DB::connection('mysql')->table('analytics_pageviews')->where('id', '>', $this->maxView)->first();
        $this->assertNotEmpty($row);
        $this->assertStringNotContainsString('41.248.0.1', json_encode($row));
        $this->assertArrayNotHasKey('ip', $row);
        $this->assertMatchesRegularExpression('~^[0-9a-f]{32}$~', $row['visitor_hash']);

        foreach ($response->headers->getCookies() as $cookie) {
            $this->assertNotSame('analytics', strtolower($cookie->getName()));
        }
        // The tracker script writes nothing on the device.
        preg_match('~<script>\s*\(function \(\) \{\s*if \(navigator\.webdriver\).*?</script>~s', $this->get(self::HOST . '/')->getContent(), $m);
        $this->assertNotEmpty($m);
        $this->assertStringNotContainsString('localStorage', $m[0]);
        $this->assertStringNotContainsString('document.cookie', $m[0]);
    }

    public function test_visitor_ids_rotate_every_day_and_old_salts_are_destroyed(): void
    {
        $payload = $this->payloadFrom(self::HOST . '/');
        $this->beacon($payload);
        $today = $this->newViews()->first()->visitor_hash;

        $this->travel(1)->days();
        $payload = $this->payloadFrom(self::HOST . '/');
        $this->beacon($payload);
        $tomorrow = $this->newViews()->get()->last()->visitor_hash;

        $this->assertNotSame($today, $tomorrow, 'The same person is not linkable across days.');

        $this->artisan('analytics:prune')->assertSuccessful();
        $this->assertSame(0, DB::connection('mysql')->table('analytics_salts')
            ->where('salt_date', '<', now(config('analytics.timezone'))->toDateString())->count());
    }

    public function test_the_retention_policy_deletes_old_rows(): void
    {
        AnalyticsPageview::create([
            'viewed_at' => now()->subDays(500), 'view_date' => now()->subDays(500)->toDateString(),
            'visitor_hash' => str_repeat('0', 32), 'is_entrance' => true, 'path' => '/', 'device' => 'desktop', 'source' => 'direct',
        ]);
        $recent = AnalyticsPageview::create([
            'viewed_at' => now()->subDays(3), 'view_date' => now()->subDays(3)->toDateString(),
            'visitor_hash' => str_repeat('1', 32), 'is_entrance' => true, 'path' => '/', 'device' => 'desktop', 'source' => 'direct',
        ]);

        $this->artisan('analytics:prune')->assertSuccessful();

        $this->assertSame([$recent->id], $this->newViews()->pluck('id')->all());
    }

    public function test_utm_values_that_look_like_personal_data_are_dropped(): void
    {
        $payload = $this->payloadFrom(self::HOST . '/');
        $this->beacon($payload + ['us' => 'john.doe@example.com', 'um' => 'email', 'uc' => '<script>x</script>']);

        $view = $this->newViews()->first();
        $this->assertNull($view->utm_source);
        $this->assertNull($view->utm_campaign);
        $this->assertSame('email', $view->utm_medium);
        $this->assertSame('campaign', $view->source);
    }

    // ── Country detection ───────────────────────────────────────────────

    public function test_country_detection_uses_the_local_database_and_never_guesses(): void
    {
        $resolver = new CountryResolver();
        if (! $resolver->isAvailable()) {
            $this->markTestSkipped('GeoIP database not installed (php artisan analytics:geoip-update).');
        }

        $this->assertSame('US', $resolver->countryCode('8.8.8.8'));
        $this->assertSame('MA', $resolver->countryCode('41.248.0.1'));
        $this->assertNull($resolver->countryCode('127.0.0.1'));
        $this->assertNull($resolver->countryCode('192.168.1.10'));
        $this->assertNull($resolver->countryCode('not-an-ip'));

        config(['analytics.geoip.database' => '/nonexistent.mmdb']);
        $this->assertNull((new CountryResolver())->countryCode('8.8.8.8'), 'No database means "unknown", not a guess.');
    }

    public function test_a_spoofed_forwarded_for_header_is_ignored(): void
    {
        $payload = $this->payloadFrom(self::HOST . '/');
        // Peer address is private (unknown country); the header claims the US.
        $this->beacon($payload, ['REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_FORWARDED_FOR' => '8.8.8.8']);

        $this->assertNull($this->newViews()->first()->country_code);
    }

    // ── Classification ──────────────────────────────────────────────────

    public function test_traffic_sources_are_classified_without_calling_unknown_traffic_search(): void
    {
        $c = new TrafficSourceClassifier();
        $own = ['fakturalista.com', 'www.fakturalista.com'];

        $this->assertSame('search', $c->classify('https://www.google.co.ma/', [], $own)['source']);
        $this->assertSame('bing', $c->classify('https://www.bing.com/search?q=x', [], $own)['source_name']);
        $this->assertSame('social', $c->classify('https://lnkd.in/abc', [], $own)['source']);
        $this->assertSame('ai', $c->classify('https://chatgpt.com/', [], $own)['source']);
        $this->assertSame('ai', $c->classify('https://gemini.google.com/app', [], $own)['source']);
        $this->assertSame(['source' => 'referral', 'source_name' => 'example.org', 'referrer_host' => 'example.org'], $c->classify('https://example.org/page', [], $own));
        $this->assertSame('direct', $c->classify('', [], $own)['source']);
        $this->assertSame('internal', $c->classify('https://fakturalista.com/pricing', [], $own)['source']);
        $this->assertSame('campaign', $c->classify('https://www.google.com/', ['utm_source' => 'google', 'utm_medium' => 'cpc'], $own)['source']);
    }

    public function test_device_detection(): void
    {
        $c = new UserAgentClassifier();
        $this->assertSame('mobile', $c->device('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148'));
        $this->assertSame('tablet', $c->device('Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15'));
        $this->assertSame('mobile', $c->device('Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 Chrome/129 Mobile Safari/537.36'));
        $this->assertSame('desktop', $c->device(self::UA));
    }

    // ── Visits ──────────────────────────────────────────────────────────

    public function test_a_visit_spans_page_views_until_thirty_minutes_of_inactivity(): void
    {
        $this->beacon($this->payloadFrom(self::HOST . '/') + ['r' => 'https://www.bing.com/']);
        $this->beacon($this->payloadFrom(self::HOST . '/pricing') + ['r' => self::HOST . '/']);
        $this->travel(31)->minutes();
        $this->beacon($this->payloadFrom(self::HOST . '/faq'));

        $views = $this->newViews()->get();
        $this->assertSame([true, false, true], $views->pluck('is_entrance')->all());
        $this->assertSame(['search', 'internal', 'direct'], $views->pluck('source')->all());
    }

    // ── Conversions ─────────────────────────────────────────────────────

    public function test_a_registration_is_recorded_only_when_signup_succeeds_and_attributed_to_the_visit(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $server = ['HTTP_USER_AGENT' => self::UA, 'REMOTE_ADDR' => '41.248.0.1'];

        // Visit arriving from LinkedIn, then the signup page.
        $this->beacon($this->payloadFrom(self::HOST . '/') + ['r' => 'https://www.linkedin.com/feed/']);

        // A failed signup (validation error) is not a registration.
        $this->withServerVariables($server)->post(self::HOST . '/register', ['name' => '', 'email' => 'x', 'password' => '1']);
        $this->assertSame(0, AnalyticsConversion::where('id', '>', $this->maxConversion)->count());

        // A successful one is - without any personal data.
        $this->get(self::HOST . '/register');
        $email = 'analytics+' . uniqid() . '@example.com';
        $this->withServerVariables($server)->post(self::HOST . '/register', [
            'name' => 'Visiteur Test', 'email' => $email, 'password' => 'SecurePass123', 'captcha_answer' => session('math_captcha_answer'),
        ])->assertRedirect();

        try {
            $conversion = AnalyticsConversion::where('id', '>', $this->maxConversion)->sole();
            $this->assertSame(AnalyticsConversion::FREE_TRIAL_SIGNUP, $conversion->type);
            $this->assertSame('social', $conversion->source);
            $this->assertSame('linkedin', $conversion->source_name);
            $this->assertSame('MA', $conversion->country_code);
            $this->assertStringNotContainsString($email, json_encode($conversion->getAttributes()));
        } finally {
            \App\Models\Tenant::where('owner_email', $email)->get()->each->delete();
        }
    }

    public function test_tracking_failures_never_break_pages_or_the_beacon(): void
    {
        $payload = $this->payloadFrom(self::HOST . '/');

        // The visitor id needs the database: make it fail.
        $this->mock(\App\Services\Analytics\VisitorIdentifier::class, fn ($mock) => $mock
            ->shouldReceive('forRequest')->andThrow(new \RuntimeException('database down')));

        $this->beacon($payload)->assertNoContent();
        $this->assertSame(0, $this->newViews()->count());
        $this->get(self::HOST . '/pricing')->assertOk();
    }
}
