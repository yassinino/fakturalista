<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\SignupLoginTicket;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Tester feedback: (1) no second login right after registration, (2) the
 * "Votre entreprise" onboarding screen can be skipped.
 *
 * Registration -> one-time ticket in the redirect's URL fragment ->
 * POST /api/signup-ticket on the new tenant -> normal Passport token ->
 * onboarding ("Votre entreprise") -> complete OR skip -> dashboard.
 */
class SignupAutoLoginAndOnboardingSkipTest extends TestCase
{
    private array $tenantIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        foreach ($this->tenantIds as $id) {
            Tenant::find($id)?->delete();
        }
        parent::tearDown();
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    /** Real self-service registration; returns [tenant, domain, email, ticket]. */
    private function register(): array
    {
        $this->get('http://fakturalista.test/register');
        $email = 'signup+' . uniqid() . '@example.com';

        $response = $this->post('http://fakturalista.test/register', [
            'name'           => 'Nadia Test',
            'email'          => $email,
            'password'       => 'SecurePass123',
            'captcha_answer' => session('math_captcha_answer'),
        ]);
        $response->assertRedirect();

        $tenant = Tenant::where('owner_email', $email)->firstOrFail();
        $this->tenantIds[] = $tenant->getTenantKey();
        $domain = $tenant->domains->first()->domain;

        $location = $response->headers->get('Location');
        $this->assertMatchesRegularExpression('~#signup=([A-Za-z0-9]{64})$~', $location);
        preg_match('~#signup=([A-Za-z0-9]{64})$~', $location, $m);

        return [$tenant, $domain, $email, $m[1]];
    }

    private function api(string $domain, string $path): string
    {
        return 'http://' . $domain . '/api/' . $path;
    }

    /** Exchanges the ticket and returns a Bearer header for the new user. */
    private function autoLogin(string $domain, string $ticket): array
    {
        $token = $this->postJson($this->api($domain, 'signup-ticket'), ['ticket' => $ticket])
            ->assertOk()->json('data.accessToken');

        return ['Authorization' => 'Bearer ' . $token];
    }

    private function profile(Tenant $tenant): array
    {
        return $tenant->run(fn () => CompanyProfile::first()?->only([
            'trade_name', 'legal_name', 'phone', 'ice', 'tax_id', 'onboarding_completed_at', 'country_code', 'currency',
        ]) ?? []);
    }

    // ── 1. Auto-login after registration ────────────────────────────────

    public function test_registration_automatically_authenticates_the_new_user(): void
    {
        [$tenant, $domain, $email, $ticket] = $this->register();

        $response = $this->postJson($this->api($domain, 'signup-ticket'), ['ticket' => $ticket])->assertOk();

        // Same payload shape as POST /login.
        $this->assertSame($email, $response->json('data.user.email'));
        $this->assertNotEmpty($response->json('data.accessToken'));
        $this->assertFalse($response->json('data.billing.onboarding_completed'), 'A brand-new account goes to onboarding next.');
        $this->assertNotNull($response->json('data.company_context'));

        // The token is a real session for that user.
        $this->getJson($this->api($domain, 'user'), ['Authorization' => 'Bearer ' . $response->json('data.accessToken')])
            ->assertOk()->assertJsonPath('user.email', $email);

        // Only the hash is stored.
        $this->assertFalse(SignupLoginTicket::where('token_hash', $ticket)->exists());
        $this->assertTrue(SignupLoginTicket::where('token_hash', hash('sha256', $ticket))->exists());
    }

    public function test_registration_does_not_send_the_user_back_to_a_password_prompt(): void
    {
        [, $domain, $email, $ticket] = $this->register();

        // The landing page carries a usable ticket (fragment: never sent to
        // servers or in Referer), so no password is needed to get in.
        $headers = $this->autoLogin($domain, $ticket);
        $this->getJson($this->api($domain, 'onboarding'), $headers)->assertOk()->assertJsonPath('onboarding_completed', false);
    }

    public function test_a_signup_ticket_is_single_use_short_lived_and_tenant_bound(): void
    {
        [$tenantA, $domainA, , $ticketA] = $this->register();
        [, $domainB, , $ticketB] = $this->register();

        // Wrong tenant: A's ticket on B's domain.
        $this->postJson($this->api($domainB, 'signup-ticket'), ['ticket' => $ticketA])->assertUnauthorized();

        // Single use.
        $this->postJson($this->api($domainA, 'signup-ticket'), ['ticket' => $ticketA])->assertOk();
        $this->postJson($this->api($domainA, 'signup-ticket'), ['ticket' => $ticketA])->assertUnauthorized();

        // Expired after the TTL.
        $this->travel(3)->minutes();
        $this->postJson($this->api($domainB, 'signup-ticket'), ['ticket' => $ticketB])->assertUnauthorized();

        // Garbage.
        $this->postJson($this->api($domainA, 'signup-ticket'), ['ticket' => str_repeat('x', 64)])->assertUnauthorized();
        $this->postJson($this->api($domainA, 'signup-ticket'), ['ticket' => 'short'])->assertUnauthorized();
        $this->postJson($this->api($domainA, 'signup-ticket'), [])->assertUnauthorized();
    }

    // ── 2. "Votre entreprise": complete or skip ─────────────────────────

    public function test_company_onboarding_can_still_be_completed_normally(): void
    {
        [$tenant, $domain, , $ticket] = $this->register();
        $headers = $this->autoLogin($domain, $ticket);

        $this->postJson($this->api($domain, 'onboarding'), [
            'trade_name' => 'Atelier Nadia', 'country' => 'Morocco', 'country_code' => 'MA', 'currency' => 'MAD',
        ], $headers)->assertOk()->assertJsonPath('onboarding_completed', true);

        $profile = $this->profile($tenant);
        $this->assertSame('Atelier Nadia', $profile['trade_name']);
        $this->assertNotNull($profile['onboarding_completed_at']);
        $this->getJson($this->api($domain, 'user'), $headers)->assertJsonPath('billing.onboarding_completed', true);
    }

    public function test_company_onboarding_can_be_skipped_without_inventing_company_data(): void
    {
        [$tenant, $domain, , $ticket] = $this->register();
        $headers = $this->autoLogin($domain, $ticket);
        $trialBefore = Tenant::find($tenant->getTenantKey())->trial_ends_at;

        $this->postJson($this->api($domain, 'onboarding/skip'), [
            'trade_name' => '   ',              // nothing real typed -> not stored
            'phone'      => '+212600000000',    // typed -> kept
        ], $headers)->assertOk()->assertJsonPath('onboarding_completed', true)->assertJsonPath('skipped', true);

        $profile = $this->profile($tenant);
        $this->assertNotNull($profile['onboarding_completed_at']);
        $this->assertNull($profile['trade_name']);
        $this->assertEmpty($profile['legal_name']); // schema default '' - nothing invented
        $this->assertNull($profile['ice']);
        $this->assertNull($profile['tax_id']);
        $this->assertSame('+212600000000', $profile['phone']);
        // Provisioning-time defaults stay (country/currency are not invented).
        $this->assertNotEmpty($profile['country_code']);
        $this->assertNotEmpty($profile['currency']);

        // Trial handled exactly like a completed onboarding.
        $tenant = Tenant::find($tenant->getTenantKey());
        $this->assertSame('trialing', $tenant->subscription_status);
        $this->assertNotNull($tenant->trial_ends_at);
        $this->assertTrue($tenant->trial_ends_at->gte($trialBefore));
    }

    public function test_a_skipped_onboarding_reaches_the_dashboard_and_nothing_crashes_without_company_info(): void
    {
        [, $domain, , $ticket] = $this->register();
        $headers = $this->autoLogin($domain, $ticket);

        // Before: business routes are gated behind onboarding.
        $this->getJson($this->api($domain, 'stats/counts'), $headers)->assertStatus(403);

        $this->postJson($this->api($domain, 'onboarding/skip'), [], $headers)->assertOk();

        $this->getJson($this->api($domain, 'user'), $headers)->assertOk()->assertJsonPath('billing.onboarding_completed', true);

        // Dashboard + the main lists all work with an empty company profile.
        foreach (['stats/counts', 'stats/cash-overview', 'invoices', 'quotes', 'customers', 'settings', 'payments/summary'] as $path) {
            $this->getJson($this->api($domain, $path), $headers)->assertOk();
        }

        // Settings is where the rest gets completed later: its data loads,
        // with the company fields simply empty.
        $settings = $this->getJson($this->api($domain, 'settings'), $headers)->json('settings');
        $this->assertEmpty($settings['legal_name'] ?? null);
        $this->assertEmpty($settings['trade_name'] ?? null);
    }

    // ── 3. Existing users ───────────────────────────────────────────────

    public function test_existing_users_are_unaffected(): void
    {
        [$tenant, $domain, $email, $ticket] = $this->register();
        $headers = $this->autoLogin($domain, $ticket);
        $this->postJson($this->api($domain, 'onboarding'), [
            'trade_name' => 'Existing Co', 'country' => 'Morocco', 'country_code' => 'MA', 'currency' => 'MAD',
        ], $headers)->assertOk();
        $before = $this->profile($tenant);
        $trialBefore = Tenant::find($tenant->getTenantKey())->trial_ends_at?->toIso8601String();

        // Normal email/password login still works exactly as before...
        // (a fresh HTTP request starts on the default 'web' guard; the test
        // app instance is reused, and auth:api's shouldUse('api') rewrote
        // auth.defaults.guard, so put back what a new request would have)
        $this->app['auth']->shouldUse('web');
        $this->postJson($this->api($domain, 'login'), ['email' => $email, 'password' => 'SecurePass123'])
            ->assertOk()->assertJsonPath('data.billing.onboarding_completed', true);
        $this->postJson($this->api($domain, 'login'), ['email' => $email, 'password' => 'wrong-password'])
            ->assertUnauthorized();

        // ...and an already-onboarded workspace is never reset by "skip".
        $this->postJson($this->api($domain, 'onboarding/skip'), ['trade_name' => 'Overwrite?'], $headers)
            ->assertOk()->assertJsonMissingPath('skipped');
        $this->assertEquals($before, $this->profile($tenant)); // value comparison (Carbon dates)
        $this->assertSame($trialBefore, Tenant::find($tenant->getTenantKey())->trial_ends_at?->toIso8601String());
    }

    public function test_skipping_requires_authentication(): void
    {
        [, $domain] = $this->register();

        $this->postJson($this->api($domain, 'onboarding/skip'))->assertUnauthorized();
    }
}
