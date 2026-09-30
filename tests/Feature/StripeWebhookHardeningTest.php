<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoicePaymentAttempt;
use App\Models\StripeConnectAccount;
use App\Models\Subscription as AppSubscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ClientPortal\ClientPortalService;
use App\Services\ClientPortal\PortalInvoicePaymentService;
use App\Services\StripeConnectService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Support\FakeStripe;
use Tests\TestCase;

/**
 * Step 6A.1 - Stripe webhook hardening and Connect consolidation.
 * Offline: Stripe's network layer is Tests\Support\FakeStripe; webhooks are
 * signed with test-only secrets so signature verification runs for real.
 */
class StripeWebhookHardeningTest extends TestCase
{
    private const PLATFORM_SECRET = 'whsec_test_platform_hardening';
    private const CONNECT_SECRET  = 'whsec_test_connect_hardening';

    private FakeStripe $stripe;
    private array $tenants = [];

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.stripe.secret'                 => 'sk_test_offline_fake',
            'services.stripe.webhook_secret'         => self::PLATFORM_SECRET,
            'services.stripe.connect_webhook_secret' => self::CONNECT_SECRET,
        ]);
        $this->stripe = FakeStripe::install();
    }

    protected function tearDown(): void
    {
        FakeStripe::uninstall();
        tenancy()->end();
        foreach ($this->tenants as $tenant) {
            $tenant->delete();
        }
        parent::tearDown();
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    private function makeTenant(?string $account = 'acct_hardening_A', array $profile = []): array
    {
        $tenant = Tenant::create(['id' => 'test-wh-' . uniqid()]);
        $domain = $tenant->id . '.fakturalista.test';
        $tenant->domains()->create(['domain' => $domain]);
        $this->tenants[] = $tenant;

        $tenant->run(function () use ($account, $profile) {
            User::factory()->create();
            $company = CompanyProfile::create(array_merge([
                'legal_name' => 'Seller Co', 'country_code' => 'MA', 'currency' => 'MAD', 'locale' => 'fr',
                'invoice_prefix' => 'INV', 'onboarding_completed_at' => now(),
                'stripe_account_id' => $account, 'onboarding_completed' => (bool) $account, 'charges_enabled' => (bool) $account,
            ], $profile));
            app(StripeConnectService::class)->syncCentralMapping($company);
        });

        return [$tenant, $domain];
    }

    private function makeInvoice(Tenant $tenant, array $overrides = []): Invoice
    {
        return $tenant->run(fn () => Invoice::create(array_merge([
            'uuid' => Str::uuid()->toString(), 'reference' => 'INV-WH-' . uniqid(),
            'customer_id' => Customer::factory()->create(['type' => 1])->id,
            'date' => now()->toDateString(), 'expiration_date' => now()->addDays(30)->toDateString(),
            'status' => Invoice::STATUS_ISSUED, 'sub_total' => 250, 'vta' => 0,
            'vta4' => 0, 'vta10' => 0, 'vta21' => 0, 'total' => 250,
        ], $overrides)));
    }

    private function invoiceStatus(Tenant $tenant, Invoice $invoice): string
    {
        return $tenant->run(fn () => Invoice::find($invoice->id)->status);
    }

    private function profile(Tenant $tenant): array
    {
        return $tenant->run(fn () => CompanyProfile::first()->only(['stripe_account_id', 'charges_enabled', 'onboarding_completed', 'payouts_enabled']));
    }

    private function signedPost(string $url, array $event, ?string $secret)
    {
        $payload = json_encode($event);
        $headers = ['CONTENT_TYPE' => 'application/json'];
        if ($secret !== null) {
            $t = time();
            $headers['HTTP_STRIPE_SIGNATURE'] = "t={$t},v1=" . hash_hmac('sha256', "{$t}.{$payload}", $secret);
        }

        return $this->call('POST', $url, [], [], [], $headers, $payload);
    }

    private function event(string $type, array $object, ?string $account = null): array
    {
        return ['id' => 'evt_' . uniqid(), 'object' => 'event', 'type' => $type, 'account' => $account, 'data' => ['object' => $object]];
    }

    private function legacySession(Tenant $tenant, Invoice $invoice, string $sessionId, array $overrides = []): array
    {
        return array_replace_recursive([
            'id' => $sessionId, 'object' => 'checkout.session', 'payment_status' => 'paid', 'status' => 'complete',
            'amount_total' => 25000, 'currency' => 'mad',
            'metadata' => ['invoice_uuid' => $invoice->uuid, 'tenant_id' => $tenant->getTenantKey()],
        ], $overrides);
    }

    // ── 1. Fail closed + signatures ─────────────────────────────────────

    public function test_every_stripe_webhook_fails_closed_without_its_secret(): void
    {
        [$tenant, $domain] = $this->makeTenant();
        $invoice = $this->makeInvoice($tenant, ['stripe_session_id' => 'cs_legacy_failclosed']);
        $event   = $this->event('checkout.session.completed', $this->legacySession($tenant, $invoice, 'cs_legacy_failclosed'));

        config(['services.stripe.webhook_secret' => null, 'services.stripe.connect_webhook_secret' => null]);

        // Unsigned AND "signed" with any key: refused before being read.
        foreach ([null, 'whsec_anything'] as $secret) {
            $this->signedPost('/stripe/webhook', $event, $secret)->assertStatus(503);
            $this->signedPost('/stripe/connect/webhook', $event + ['account' => 'acct_hardening_A'], $secret)->assertStatus(503);
            $this->signedPost('http://' . $domain . '/payment/webhook', $event, $secret)->assertStatus(503);
        }

        $this->assertSame(Invoice::STATUS_ISSUED, $this->invoiceStatus($tenant, $invoice));
    }

    public function test_every_stripe_webhook_rejects_an_invalid_or_missing_signature(): void
    {
        [$tenant, $domain] = $this->makeTenant();
        $invoice = $this->makeInvoice($tenant, ['stripe_session_id' => 'cs_legacy_badsig']);
        $event   = $this->event('checkout.session.completed', $this->legacySession($tenant, $invoice, 'cs_legacy_badsig'));

        foreach ([null, 'whsec_attacker'] as $secret) {
            $this->signedPost('/stripe/webhook', $event, $secret)->assertStatus(400);
            $this->signedPost('/stripe/connect/webhook', $event, $secret)->assertStatus(400);
            $this->signedPost('http://' . $domain . '/payment/webhook', $event, $secret)->assertStatus(400);
        }

        $this->assertSame(Invoice::STATUS_ISSUED, $this->invoiceStatus($tenant, $invoice));
    }

    // ── 2. account.updated through the central Connect webhook ──────────

    public function test_account_updated_through_the_central_webhook_updates_only_the_owning_tenant(): void
    {
        [$tenantA] = $this->makeTenant('acct_hardening_A');
        [$tenantB] = $this->makeTenant('acct_hardening_B');

        $this->signedPost('/stripe/connect/webhook', $this->event('account.updated', [
            'id' => 'acct_hardening_A', 'object' => 'account',
            'details_submitted' => true, 'charges_enabled' => false, 'payouts_enabled' => true,
        ], 'acct_hardening_A'), self::CONNECT_SECRET)->assertOk();

        $this->assertFalse((bool) $this->profile($tenantA)['charges_enabled']);
        $this->assertTrue((bool) $this->profile($tenantA)['payouts_enabled']);
        $this->assertTrue((bool) $this->profile($tenantB)['charges_enabled'], 'Tenant B must be untouched.');
    }

    public function test_account_updated_is_ignored_when_unmapped_or_when_the_payload_disagrees_with_the_signed_account(): void
    {
        [$tenantA] = $this->makeTenant('acct_hardening_A');

        $disable = ['object' => 'account', 'details_submitted' => true, 'charges_enabled' => false];

        // Unknown account -> nothing (no tenant scan).
        $this->signedPost('/stripe/connect/webhook', $this->event('account.updated', ['id' => 'acct_unknown'] + $disable, 'acct_unknown'), self::CONNECT_SECRET)->assertOk();
        // Payload claims A, but the signed event is about another account.
        $this->signedPost('/stripe/connect/webhook', $this->event('account.updated', ['id' => 'acct_hardening_A'] + $disable, 'acct_unknown'), self::CONNECT_SECRET)->assertOk();
        // No event account at all (a platform-account event).
        $this->signedPost('/stripe/connect/webhook', $this->event('account.updated', ['id' => 'acct_hardening_A'] + $disable, null), self::CONNECT_SECRET)->assertOk();

        $this->assertTrue((bool) $this->profile($tenantA)['charges_enabled']);
    }

    public function test_the_central_account_map_follows_link_and_disconnect(): void
    {
        [$tenant] = $this->makeTenant('acct_hardening_A');
        $this->assertSame($tenant->getTenantKey(), StripeConnectAccount::where('stripe_account_id', 'acct_hardening_A')->value('tenant_id'));

        // Switching accounts replaces the row; disconnecting removes it.
        $tenant->run(function () {
            $profile = CompanyProfile::first();
            $profile->update(['stripe_account_id' => 'acct_hardening_A2']);
            app(StripeConnectService::class)->syncCentralMapping($profile);
        });
        $this->assertFalse(StripeConnectAccount::where('stripe_account_id', 'acct_hardening_A')->exists());
        $this->assertSame($tenant->getTenantKey(), StripeConnectAccount::where('stripe_account_id', 'acct_hardening_A2')->value('tenant_id'));

        Http::fake(['connect.stripe.com/*' => Http::response(['stripe_user_id' => 'acct_hardening_A2'])]);
        $tenant->run(fn () => app(StripeConnectService::class)->disconnect(CompanyProfile::first()));
        $this->assertFalse(StripeConnectAccount::where('tenant_id', $tenant->getTenantKey())->exists());
    }

    public function test_the_tenant_domain_connect_webhook_is_retired(): void
    {
        [$tenant, $domain] = $this->makeTenant('acct_hardening_A');

        $response = $this->signedPost('http://' . $domain . '/connect/webhook', $this->event('account.updated', [
            'id' => 'acct_hardening_A', 'object' => 'account', 'details_submitted' => true, 'charges_enabled' => false,
        ], 'acct_hardening_A'), self::CONNECT_SECRET);

        $this->assertContains($response->getStatusCode(), [404, 405]);
        $this->assertTrue((bool) $this->profile($tenant)['charges_enabled']);
    }

    // ── 3. /pay: no platform-account fallback, same architecture ────────

    public function test_pay_link_never_falls_back_to_the_platform_account(): void
    {
        [$tenantNoAccount, $domainNoAccount] = $this->makeTenant(null);
        $invoice1 = $this->makeInvoice($tenantNoAccount);
        $this->get('http://' . $domainNoAccount . '/pay/' . $invoice1->uuid)->assertStatus(503);

        [$tenantDisabled, $domainDisabled] = $this->makeTenant('acct_hardening_off', ['charges_enabled' => false]);
        $invoice2 = $this->makeInvoice($tenantDisabled);
        $this->get('http://' . $domainDisabled . '/pay/' . $invoice2->uuid)->assertStatus(503);

        $user = $tenantDisabled->run(fn () => User::first());
        $this->actingAs($user, 'api')
            ->postJson('http://' . $domainDisabled . '/api/invoices/' . $invoice2->uuid . '/create-payment-link')
            ->assertStatus(422);

        $this->assertCount(0, $this->stripe->requests, 'No Stripe call at all - in particular none on the platform account.');
    }

    public function test_pay_link_creates_the_checkout_on_the_sellers_account_and_the_connect_webhook_settles_it(): void
    {
        [$tenant, $domain] = $this->makeTenant('acct_hardening_A');
        $invoice = $this->makeInvoice($tenant);

        $response = $this->get('http://' . $domain . '/pay/' . $invoice->uuid);
        $response->assertRedirect();

        $create = $this->stripe->creates()[0];
        $this->assertSame('acct_hardening_A', $create['headers']['Stripe-Account'] ?? null);
        $this->assertSame(PortalInvoicePaymentService::PURPOSE_PAY_LINK, $create['params']['metadata']['purpose']);
        $this->assertSame(25000, (int) $create['params']['line_items'][0]['price_data']['unit_amount']);
        $this->assertStringEndsWith('/pay/' . $invoice->uuid . '/success', $create['params']['success_url']);

        // Same visitor again -> same session, no second Checkout.
        $this->get('http://' . $domain . '/pay/' . $invoice->uuid)->assertRedirect($response->headers->get('Location'));
        $this->assertCount(1, $this->stripe->creates());

        // The success page alone changes nothing.
        $this->get('http://' . $domain . '/pay/' . $invoice->uuid . '/success')->assertOk();
        $this->assertSame(Invoice::STATUS_ISSUED, $this->invoiceStatus($tenant, $invoice));

        $attempt = $tenant->run(fn () => InvoicePaymentAttempt::where('invoice_id', $invoice->id)->first());
        $this->signedPost('/stripe/connect/webhook', $this->event('checkout.session.completed', [
            'id' => $attempt->stripe_session_id, 'object' => 'checkout.session', 'payment_status' => 'paid',
            'amount_total' => 25000, 'currency' => 'mad', 'payment_intent' => 'pi_paylink',
            'metadata' => ['purpose' => PortalInvoicePaymentService::PURPOSE_PAY_LINK, 'tenant_id' => $tenant->getTenantKey(),
                           'invoice_uuid' => $invoice->uuid, 'attempt_uuid' => $attempt->uuid],
        ], 'acct_hardening_A'), self::CONNECT_SECRET)->assertOk();

        $paid = $tenant->run(fn () => Invoice::find($invoice->id));
        $this->assertSame(Invoice::STATUS_PAID, $paid->status);
        $this->assertSame('stripe', $paid->paid_via);
        $this->assertSame($attempt->stripe_session_id, $paid->stripe_session_id, 'Kept as the transaction reference shown in the payments list.');
    }

    public function test_a_portal_session_is_never_handed_to_a_pay_link_visitor(): void
    {
        [$tenant, $domain] = $this->makeTenant('acct_hardening_A');
        [$invoice, $token] = $tenant->run(function () {
            $customer = Customer::factory()->create(['type' => 1]);
            $invoice  = Invoice::create([
                'uuid' => Str::uuid()->toString(), 'reference' => 'INV-X-' . uniqid(), 'customer_id' => $customer->id,
                'date' => now()->toDateString(), 'expiration_date' => now()->addDays(30)->toDateString(),
                'status' => Invoice::STATUS_ISSUED, 'sub_total' => 250, 'vta' => 0, 'vta4' => 0, 'vta10' => 0, 'vta21' => 0, 'total' => 250,
            ]);
            return [$invoice, app(ClientPortalService::class)->createAccess($customer)];
        });

        $portalUrl = $this->postJson('http://' . $domain . '/api/portal/' . $token . '/invoices/' . $invoice->uuid . '/payment/stripe')
            ->assertOk()->json('checkout_url');

        $payRedirect = $this->get('http://' . $domain . '/pay/' . $invoice->uuid)->headers->get('Location');

        $this->assertNotSame($portalUrl, $payRedirect);
        $this->assertStringNotContainsString($token, $this->stripe->creates()[1]['params']['success_url']);
        // Only one payable session left open for the invoice.
        $this->assertCount(1, $this->stripe->expires());
        $this->assertSame(1, $tenant->run(fn () => InvoicePaymentAttempt::where('invoice_id', $invoice->id)->where('status', 'open')->count()));
    }

    // ── 4. Sessions created by the old /pay code (rollover) ─────────────

    public function test_a_legacy_connected_session_is_settled_only_after_verification(): void
    {
        [$tenant] = $this->makeTenant('acct_hardening_A');
        $invoice  = $this->makeInvoice($tenant, ['stripe_session_id' => 'cs_legacy_ok']);

        $send = fn (array $overrides, ?string $account = 'acct_hardening_A') => $this->signedPost('/stripe/connect/webhook',
            $this->event('checkout.session.completed', $this->legacySession($tenant, $invoice, $overrides['id'] ?? 'cs_legacy_ok', $overrides), $account),
            self::CONNECT_SECRET)->assertOk();

        $send(['id' => 'cs_some_other_session']);               // not the session stored on the invoice
        $send(['amount_total' => 100]);                          // wrong amount
        $send(['currency' => 'eur']);                            // wrong currency
        $send(['payment_status' => 'unpaid']);                   // not paid yet
        $send([], 'acct_someone_else');                          // another connected account
        $this->assertSame(Invoice::STATUS_ISSUED, $this->invoiceStatus($tenant, $invoice));

        $send([]);
        $this->assertSame(Invoice::STATUS_PAID, $this->invoiceStatus($tenant, $invoice));
    }

    public function test_a_legacy_platform_session_on_the_platform_webhook_is_verified_too(): void
    {
        [$tenant] = $this->makeTenant('acct_hardening_A');
        $invoice  = $this->makeInvoice($tenant, ['stripe_session_id' => 'cs_legacy_platform']);

        // Metadata alone (the old behavior) is no longer enough.
        $this->signedPost('/stripe/webhook', $this->event('checkout.session.completed',
            $this->legacySession($tenant, $invoice, 'cs_forged_elsewhere')), self::PLATFORM_SECRET)->assertOk();
        $this->assertSame(Invoice::STATUS_ISSUED, $this->invoiceStatus($tenant, $invoice));

        $this->signedPost('/stripe/webhook', $this->event('checkout.session.completed',
            $this->legacySession($tenant, $invoice, 'cs_legacy_platform')), self::PLATFORM_SECRET)->assertOk();
        $this->assertSame(Invoice::STATUS_PAID, $this->invoiceStatus($tenant, $invoice));
    }

    // ── 5. Subscription billing stays separate ──────────────────────────

    public function test_invoice_payment_and_subscription_events_never_cross_over(): void
    {
        [$tenant] = $this->makeTenant('acct_hardening_A');
        $invoice  = $this->makeInvoice($tenant);

        // A portal/pay-link session on the platform (subscription) webhook: ignored.
        foreach (PortalInvoicePaymentService::PURPOSES as $purpose) {
            $this->signedPost('/stripe/webhook', $this->event('checkout.session.completed', [
                'id' => 'cs_cross_' . $purpose, 'object' => 'checkout.session', 'payment_status' => 'paid',
                'amount_total' => 25000, 'currency' => 'mad', 'subscription' => 'sub_cross',
                'metadata' => ['purpose' => $purpose, 'tenant_id' => $tenant->getTenantKey(), 'invoice_uuid' => $invoice->uuid, 'plan_id' => '1'],
            ]), self::PLATFORM_SECRET)->assertOk();
        }

        // A subscription-shaped session on the Connect webhook: ignored.
        $this->signedPost('/stripe/connect/webhook', $this->event('checkout.session.completed', [
            'id' => 'cs_sub_on_connect', 'object' => 'checkout.session', 'subscription' => 'sub_cross_2', 'customer' => 'cus_x',
            'metadata' => ['tenant_id' => $tenant->getTenantKey(), 'plan_id' => '1'],
        ], 'acct_hardening_A'), self::CONNECT_SECRET)->assertOk();

        $this->assertSame(Invoice::STATUS_ISSUED, $this->invoiceStatus($tenant, $invoice));
        $this->assertSame(0, AppSubscription::where('tenant_id', $tenant->getTenantKey())->count());
        $this->assertCount(0, $this->stripe->requests, 'Neither webhook went on to call Stripe for the other purpose.');
    }
}
