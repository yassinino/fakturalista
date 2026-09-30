<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceHistory;
use App\Models\InvoicePaymentAttempt;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ClientPortal\ClientPortalService;
use App\Services\ClientPortal\PortalInvoicePaymentService;
use Illuminate\Support\Str;
use Tests\Support\FakeStripe;
use Tests\TestCase;

/**
 * Client Portal Step 6A - Stripe Connect invoice payments.
 *
 * Stripe's network layer is replaced by Tests\Support\FakeStripe (stripe-php's
 * own ApiRequestor::setHttpClient() hook), so the real SDK still builds
 * every request - including the Stripe-Account and Idempotency-Key
 * headers - and the tests assert on exactly what would have been sent.
 * Webhooks are signed with a test Connect secret so signature
 * verification runs for real, same approach as SubscriptionBillingTest.
 */
class ClientPortalStripePaymentTest extends TestCase
{
    private const SELLER_ACCOUNT  = 'acct_seller_A';
    private const WEBHOOK_SECRET  = 'whsec_portal_test_secret';

    private FakeStripe $stripe;
    private array $tenants = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.stripe.secret'                 => 'sk_test_fake_portal',
            'services.stripe.connect_webhook_secret' => self::WEBHOOK_SECRET,
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

    private function makeTenant(array $profile = []): array
    {
        $id     = 'test-portal-pay-' . uniqid();
        $tenant = Tenant::create(['id' => $id]);
        $domain = $id . '.fakturalista.test';
        $tenant->domains()->create(['domain' => $domain]);
        $this->tenants[] = $tenant;

        $tenant->run(function () use ($profile) {
            User::factory()->create();
            CompanyProfile::create(array_merge([
                'legal_name'              => 'Seller Co',
                'country_code'            => 'MA',
                'currency'                => 'MAD',
                'locale'                  => 'fr',
                'invoice_prefix'          => 'INV',
                'onboarding_completed_at' => now(),
                'stripe_account_id'       => self::SELLER_ACCOUNT,
                'onboarding_completed'    => true,
                'charges_enabled'         => true,
            ], $profile));
        });

        return [$tenant, $domain];
    }

    /** @return array{0: Invoice, 1: string} invoice + raw portal token */
    private function makeInvoiceWithToken(Tenant $tenant, array $overrides = []): array
    {
        return $tenant->run(function () use ($overrides) {
            $customer = Customer::factory()->create(['type' => 1]);
            $invoice  = $this->makeInvoice($customer, $overrides);
            $token    = app(ClientPortalService::class)->createAccess($customer);

            return [$invoice, $token];
        });
    }

    private function makeInvoice(Customer $customer, array $overrides = []): Invoice
    {
        return Invoice::create(array_merge([
            'uuid'            => Str::uuid()->toString(),
            'reference'       => 'INV-PAY-' . uniqid(),
            'customer_id'     => $customer->id,
            'date'            => now()->toDateString(),
            'expiration_date' => now()->addDays(30)->toDateString(),
            'status'          => Invoice::STATUS_ISSUED,
            'sub_total'       => 1234.56,
            'vta'             => 0,
            'vta4' => 0, 'vta10' => 0, 'vta21' => 0,
            'total'           => 1234.56,
        ], $overrides));
    }

    private function payUrl(string $domain, string $token, Invoice $invoice): string
    {
        return 'http://' . $domain . '/api/portal/' . $token . '/invoices/' . $invoice->uuid . '/payment/stripe';
    }

    private function fresh(Tenant $tenant, Invoice $invoice): Invoice
    {
        return $tenant->run(fn () => Invoice::find($invoice->id));
    }

    /** Datetime casts need the tenant connection, so read them inside it. */
    private function paidAt(Tenant $tenant, Invoice $invoice): ?string
    {
        return $tenant->run(fn () => Invoice::find($invoice->id)->paid_at?->toIso8601String());
    }

    private function attempt(Tenant $tenant, Invoice $invoice): ?InvoicePaymentAttempt
    {
        return $tenant->run(fn () => InvoicePaymentAttempt::where('invoice_id', $invoice->id)->latest('id')->first());
    }

    private function paidHistoryCount(Tenant $tenant, Invoice $invoice): int
    {
        return $tenant->run(fn () => InvoiceHistory::where('invoice_id', $invoice->id)
            ->where('action', InvoiceHistory::ACTION_PAID)->count());
    }

    /** A Checkout Session object as Stripe would send it for the recorded attempt. */
    private function sessionFor(Tenant $tenant, InvoicePaymentAttempt $attempt, array $overrides = []): array
    {
        return array_replace_recursive([
            'id'             => $attempt->stripe_session_id,
            'object'         => 'checkout.session',
            'payment_status' => 'paid',
            'status'         => 'complete',
            'amount_total'   => $attempt->amount_minor,
            'currency'       => strtolower($attempt->currency),
            'payment_intent' => 'pi_test_' . uniqid(),
            'metadata'       => [
                'purpose'      => PortalInvoicePaymentService::PURPOSE,
                'tenant_id'    => $tenant->getTenantKey(),
                'invoice_uuid' => $attempt->invoice->uuid ?? '',
                'attempt_uuid' => $attempt->uuid,
            ],
        ], $overrides);
    }

    private function postWebhook(array $session, string $type = 'checkout.session.completed', ?string $account = self::SELLER_ACCOUNT, ?string $secret = null, string $path = '/stripe/connect/webhook')
    {
        $payload = json_encode([
            'id'      => 'evt_' . uniqid(),
            'object'  => 'event',
            'type'    => $type,
            'account' => $account,
            'data'    => ['object' => $session],
        ]);
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret ?? self::WEBHOOK_SECRET);

        return $this->call('POST', $path, [], [], [], [
            'CONTENT_TYPE'          => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
        ], $payload);
    }

    /** Starts a checkout for a fresh eligible invoice and returns everything a webhook test needs. */
    private function startedCheckout(): array
    {
        [$tenant, $domain] = $this->makeTenant();
        [$invoice, $token] = $this->makeInvoiceWithToken($tenant);

        $this->postJson($this->payUrl($domain, $token, $invoice))->assertOk();

        $attempt = $tenant->run(fn () => InvoicePaymentAttempt::with('invoice')->where('invoice_id', $invoice->id)->firstOrFail());

        return [$tenant, $domain, $invoice, $token, $attempt];
    }

    // ── Checkout creation ───────────────────────────────────────────────

    public function test_an_eligible_invoice_creates_a_checkout_on_the_sellers_connected_account(): void
    {
        [$tenant, $domain] = $this->makeTenant();
        [$invoice, $token] = $this->makeInvoiceWithToken($tenant);

        $response = $this->postJson($this->payUrl($domain, $token, $invoice));

        $response->assertOk();
        $this->assertSame(['checkout_url'], array_keys($response->json()));

        $this->assertCount(1, $this->stripe->creates());
        $request = $this->stripe->creates()[0];

        // Server-side amount (1234.56 -> minor units) and tenant currency.
        $this->assertSame(123456, (int) $request['params']['line_items'][0]['price_data']['unit_amount']);
        $this->assertSame('mad', $request['params']['line_items'][0]['price_data']['currency']);
        // Direct charge on the seller's connected account, idempotent.
        $this->assertSame(self::SELLER_ACCOUNT, $request['headers']['Stripe-Account'] ?? null);
        $this->assertStringStartsWith('portal-invoice-', $request['headers']['Idempotency-Key'] ?? '');
        // No platform fee is invented.
        $this->assertArrayNotHasKey('application_fee_amount', $request['params']['payment_intent_data'] ?? []);

        $metadata = $request['params']['metadata'];
        $this->assertSame(PortalInvoicePaymentService::PURPOSE, $metadata['purpose']);
        $this->assertSame($tenant->getTenantKey(), $metadata['tenant_id']);
        $this->assertSame($invoice->uuid, $metadata['invoice_uuid']);
        $this->assertSame(['purpose', 'tenant_id', 'invoice_uuid', 'attempt_uuid'], array_keys($metadata));

        $this->assertStringEndsWith('/portal/' . $token . '?payment=success', $request['params']['success_url']);
        $this->assertStringEndsWith('/portal/' . $token . '?payment=cancelled', $request['params']['cancel_url']);

        $attempt = $this->attempt($tenant, $invoice);
        $this->assertSame(InvoicePaymentAttempt::STATUS_OPEN, $attempt->status);
        $this->assertSame(123456, $attempt->amount_minor);
        $this->assertSame('MAD', $attempt->currency);
        $this->assertSame(self::SELLER_ACCOUNT, $attempt->stripe_account_id);
        $this->assertSame($response->json('checkout_url'), $attempt->checkout_url);
    }

    public function test_amount_currency_account_tenant_and_status_cannot_be_supplied_by_the_request(): void
    {
        [$tenant, $domain] = $this->makeTenant();
        [$invoice, $token] = $this->makeInvoiceWithToken($tenant);

        $this->postJson($this->payUrl($domain, $token, $invoice), [
            'amount'            => 1,
            'unit_amount'       => 1,
            'currency'          => 'usd',
            'stripe_account'    => 'acct_attacker',
            'stripe_account_id' => 'acct_attacker',
            'tenant_id'         => 'some-other-tenant',
            'status'            => 'paid',
        ])->assertOk();

        $request = $this->stripe->creates()[0];
        $this->assertSame(123456, (int) $request['params']['line_items'][0]['price_data']['unit_amount']);
        $this->assertSame('mad', $request['params']['line_items'][0]['price_data']['currency']);
        $this->assertSame(self::SELLER_ACCOUNT, $request['headers']['Stripe-Account']);
        $this->assertSame($tenant->getTenantKey(), $request['params']['metadata']['tenant_id']);
        $this->assertSame(Invoice::STATUS_ISSUED, $this->fresh($tenant, $invoice)->status);
    }

    public function test_another_customers_invoice_is_a_404(): void
    {
        [$tenant, $domain] = $this->makeTenant();
        [, $tokenA]        = $this->makeInvoiceWithToken($tenant);
        [$invoiceB]        = $this->makeInvoiceWithToken($tenant);

        $this->postJson($this->payUrl($domain, $tokenA, $invoiceB))->assertNotFound();
        $this->assertCount(0, $this->stripe->requests);
    }

    public function test_draft_cancelled_and_paid_invoices_cannot_start_a_checkout(): void
    {
        [$tenant, $domain] = $this->makeTenant();

        // Drafts are never customer-visible -> same bare 404 as "not yours".
        [$draft, $tokenDraft] = $this->makeInvoiceWithToken($tenant, ['status' => Invoice::STATUS_DRAFT]);
        $this->postJson($this->payUrl($domain, $tokenDraft, $draft))->assertNotFound();

        [$cancelled, $tokenCancelled] = $this->makeInvoiceWithToken($tenant, ['status' => Invoice::STATUS_CANCELLED]);
        $this->postJson($this->payUrl($domain, $tokenCancelled, $cancelled))
            ->assertStatus(422)->assertJsonPath('reason', 'not_payable');

        [$paid, $tokenPaid] = $this->makeInvoiceWithToken($tenant, ['status' => Invoice::STATUS_PAID, 'paid_at' => now()]);
        $this->postJson($this->payUrl($domain, $tokenPaid, $paid))
            ->assertStatus(422)
            ->assertJsonPath('reason', 'already_paid')
            ->assertJsonPath('message', __('invoice.portal_payment.already_paid'));

        $this->assertCount(0, $this->stripe->requests);
    }

    public function test_a_zero_or_negative_total_cannot_start_a_checkout(): void
    {
        [$tenant, $domain] = $this->makeTenant();

        foreach ([0, -10] as $total) {
            [$invoice, $token] = $this->makeInvoiceWithToken($tenant, ['total' => $total, 'sub_total' => $total]);
            $this->postJson($this->payUrl($domain, $token, $invoice))
                ->assertStatus(422)->assertJsonPath('reason', 'not_payable');
        }

        $this->assertCount(0, $this->stripe->requests);
    }

    public function test_an_unsupported_currency_cannot_start_a_checkout(): void
    {
        [$tenant, $domain] = $this->makeTenant(['currency' => 'JPY']);
        [$invoice, $token] = $this->makeInvoiceWithToken($tenant);

        $this->postJson($this->payUrl($domain, $token, $invoice))
            ->assertStatus(422)->assertJsonPath('reason', 'currency_unsupported');
        $this->assertCount(0, $this->stripe->requests);
    }

    public function test_a_seller_without_a_usable_connected_account_never_falls_back_to_the_platform(): void
    {
        [$tenant, $domain] = $this->makeTenant(['charges_enabled' => false]);
        [$invoice, $token] = $this->makeInvoiceWithToken($tenant);
        $this->postJson($this->payUrl($domain, $token, $invoice))
            ->assertStatus(503)->assertJsonPath('reason', 'unavailable');

        [$tenant2, $domain2] = $this->makeTenant(['stripe_account_id' => null]);
        [$invoice2, $token2] = $this->makeInvoiceWithToken($tenant2);
        $this->postJson($this->payUrl($domain2, $token2, $invoice2))->assertStatus(503);

        $this->assertCount(0, $this->stripe->requests);
    }

    public function test_invalid_and_revoked_tokens_fail(): void
    {
        [$tenant, $domain] = $this->makeTenant();
        [$invoice, $token] = $this->makeInvoiceWithToken($tenant);

        $this->postJson($this->payUrl($domain, Str::random(64), $invoice))->assertNotFound();

        $tenant->run(fn () => app(ClientPortalService::class)->revoke(Customer::find($invoice->customer_id)));
        $this->postJson($this->payUrl($domain, $token, $invoice))->assertStatus(410);

        $this->assertCount(0, $this->stripe->requests);
    }

    public function test_repeated_requests_reuse_the_open_session_instead_of_creating_another(): void
    {
        [$tenant, $domain] = $this->makeTenant();
        [$invoice, $token] = $this->makeInvoiceWithToken($tenant);

        $first  = $this->postJson($this->payUrl($domain, $token, $invoice))->assertOk()->json('checkout_url');
        $second = $this->postJson($this->payUrl($domain, $token, $invoice))->assertOk()->json('checkout_url');
        $third  = $this->postJson($this->payUrl($domain, $token, $invoice))->assertOk()->json('checkout_url');

        $this->assertSame($first, $second);
        $this->assertSame($first, $third);
        $this->assertCount(1, $this->stripe->creates());
        $this->assertSame(1, $tenant->run(fn () => InvoicePaymentAttempt::where('invoice_id', $invoice->id)->count()));
    }

    public function test_an_expiring_session_is_closed_before_a_new_one_is_opened(): void
    {
        [$tenant, $domain, $invoice, $token, $attempt] = $this->startedCheckout();

        // Too close to expiry to hand out again.
        $tenant->run(fn () => InvoicePaymentAttempt::whereKey($attempt->id)->update(['expires_at' => now()->addMinutes(5)]));

        $this->postJson($this->payUrl($domain, $token, $invoice))->assertOk();

        $this->assertCount(2, $this->stripe->creates());
        $this->assertCount(1, $this->stripe->expires());
        $statuses = $tenant->run(fn () => InvoicePaymentAttempt::where('invoice_id', $invoice->id)->orderBy('id')->pluck('status')->all());
        $this->assertSame([InvoicePaymentAttempt::STATUS_EXPIRED, InvoicePaymentAttempt::STATUS_OPEN], $statuses);
    }

    public function test_a_session_already_being_paid_blocks_a_second_checkout(): void
    {
        [$tenant, $domain, $invoice, $token, $attempt] = $this->startedCheckout();

        $tenant->run(fn () => InvoicePaymentAttempt::whereKey($attempt->id)->update(['expires_at' => now()->addMinutes(5)]));
        $this->stripe->remoteStatus = 'complete';

        $this->postJson($this->payUrl($domain, $token, $invoice))
            ->assertStatus(409)->assertJsonPath('reason', 'in_progress');

        $this->assertCount(1, $this->stripe->creates());
    }

    // ── Webhook ─────────────────────────────────────────────────────────

    public function test_a_verified_webhook_marks_the_invoice_paid(): void
    {
        [$tenant, , $invoice, , $attempt] = $this->startedCheckout();

        $this->postWebhook($this->sessionFor($tenant, $attempt))->assertOk();

        $fresh = $this->fresh($tenant, $invoice);
        $this->assertSame(Invoice::STATUS_PAID, $fresh->status);
        $this->assertNotNull($this->paidAt($tenant, $invoice));
        $this->assertSame('stripe', $fresh->paid_via);
        $this->assertSame(1, $this->paidHistoryCount($tenant, $invoice));

        $settled = $this->attempt($tenant, $invoice);
        $this->assertSame(InvoicePaymentAttempt::STATUS_PAID, $settled->status);
        $this->assertNotNull($tenant->run(fn () => InvoicePaymentAttempt::find($settled->id)->completed_at));
        $this->assertNotNull($settled->stripe_payment_intent_id);
    }

    public function test_a_duplicate_webhook_is_harmless(): void
    {
        [$tenant, , $invoice, , $attempt] = $this->startedCheckout();
        $session = $this->sessionFor($tenant, $attempt);

        $this->postWebhook($session)->assertOk();
        $paidAt = $this->paidAt($tenant, $invoice);

        $this->travel(5)->minutes();
        $this->postWebhook($session)->assertOk();
        $this->postWebhook($session, 'checkout.session.async_payment_succeeded')->assertOk();

        $this->assertSame($paidAt, $this->paidAt($tenant, $invoice));
        $this->assertSame(1, $this->paidHistoryCount($tenant, $invoice));
    }

    public function test_a_forged_or_unsigned_webhook_is_rejected(): void
    {
        [$tenant, , $invoice, , $attempt] = $this->startedCheckout();

        $this->postWebhook($this->sessionFor($tenant, $attempt), secret: 'whsec_attacker')->assertStatus(400);

        $this->call('POST', '/stripe/connect/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['type' => 'checkout.session.completed', 'data' => ['object' => $this->sessionFor($tenant, $attempt)]])
        )->assertStatus(400);

        // No unsigned fallback when the secret isn't configured.
        config(['services.stripe.connect_webhook_secret' => null]);
        $this->postWebhook($this->sessionFor($tenant, $attempt))->assertStatus(503);

        $this->assertSame(Invoice::STATUS_ISSUED, $this->fresh($tenant, $invoice)->status);
        $this->assertSame(InvoicePaymentAttempt::STATUS_OPEN, $this->attempt($tenant, $invoice)->status);
    }

    public function test_an_amount_mismatch_does_not_mark_the_invoice_paid(): void
    {
        [$tenant, , $invoice, , $attempt] = $this->startedCheckout();

        $this->postWebhook($this->sessionFor($tenant, $attempt, ['amount_total' => 100]))->assertOk();

        $this->assertSame(Invoice::STATUS_ISSUED, $this->fresh($tenant, $invoice)->status);
        $settled = $this->attempt($tenant, $invoice);
        $this->assertSame(InvoicePaymentAttempt::STATUS_REJECTED, $settled->status);
        $this->assertSame('amount_mismatch', $settled->failure_reason);
    }

    public function test_a_currency_mismatch_does_not_mark_the_invoice_paid(): void
    {
        [$tenant, , $invoice, , $attempt] = $this->startedCheckout();

        $this->postWebhook($this->sessionFor($tenant, $attempt, ['currency' => 'eur']))->assertOk();

        $this->assertSame(Invoice::STATUS_ISSUED, $this->fresh($tenant, $invoice)->status);
        $this->assertSame('currency_mismatch', $this->attempt($tenant, $invoice)->failure_reason);
    }

    public function test_a_payment_on_a_different_connected_account_does_not_mark_the_invoice_paid(): void
    {
        [$tenant, , $invoice, , $attempt] = $this->startedCheckout();

        $this->postWebhook($this->sessionFor($tenant, $attempt), account: 'acct_someone_else')->assertOk();
        $this->postWebhook($this->sessionFor($tenant, $attempt), account: null)->assertOk();

        $this->assertSame(Invoice::STATUS_ISSUED, $this->fresh($tenant, $invoice)->status);
        $this->assertSame(InvoicePaymentAttempt::STATUS_OPEN, $this->attempt($tenant, $invoice)->status);
    }

    public function test_an_unpaid_completed_or_failed_or_expired_session_never_marks_the_invoice_paid(): void
    {
        [$tenant, , $invoice, , $attempt] = $this->startedCheckout();

        // Delayed payment method: completed, but money not there yet.
        $this->postWebhook($this->sessionFor($tenant, $attempt, ['payment_status' => 'unpaid']))->assertOk();
        $this->assertSame(InvoicePaymentAttempt::STATUS_OPEN, $this->attempt($tenant, $invoice)->status);

        $this->postWebhook($this->sessionFor($tenant, $attempt, ['payment_status' => 'unpaid']), 'checkout.session.async_payment_failed')->assertOk();
        $this->assertSame(InvoicePaymentAttempt::STATUS_FAILED, $this->attempt($tenant, $invoice)->status);
        $this->assertSame(Invoice::STATUS_ISSUED, $this->fresh($tenant, $invoice)->status);

        [$tenant2, , $invoice2, , $attempt2] = $this->startedCheckout();
        $this->postWebhook($this->sessionFor($tenant2, $attempt2, ['payment_status' => 'unpaid', 'status' => 'expired']), 'checkout.session.expired')->assertOk();
        $this->assertSame(InvoicePaymentAttempt::STATUS_EXPIRED, $this->attempt($tenant2, $invoice2)->status);
        $this->assertSame(Invoice::STATUS_ISSUED, $this->fresh($tenant2, $invoice2)->status);
    }

    public function test_an_invoice_cancelled_after_checkout_is_not_marked_paid(): void
    {
        [$tenant, , $invoice, , $attempt] = $this->startedCheckout();
        $tenant->run(fn () => Invoice::whereKey($invoice->id)->update(['status' => Invoice::STATUS_CANCELLED]));

        $this->postWebhook($this->sessionFor($tenant, $attempt))->assertOk();

        $this->assertSame(Invoice::STATUS_CANCELLED, $this->fresh($tenant, $invoice)->status);
        $this->assertSame('invoice_not_payable', $this->attempt($tenant, $invoice)->failure_reason);
    }

    public function test_reaching_the_success_url_alone_changes_nothing(): void
    {
        [$tenant, $domain, $invoice, $token] = $this->startedCheckout();

        $this->get('http://' . $domain . '/portal/' . $token . '?payment=success')->assertOk();

        $this->assertSame(Invoice::STATUS_ISSUED, $this->fresh($tenant, $invoice)->status);
        $this->assertSame(InvoicePaymentAttempt::STATUS_OPEN, $this->attempt($tenant, $invoice)->status);
    }

    public function test_legacy_webhooks_never_settle_a_portal_payment(): void
    {
        [$tenant, , $invoice, , $attempt] = $this->startedCheckout();

        config(['services.stripe.webhook_secret' => 'whsec_platform_test']);
        $this->postWebhook($this->sessionFor($tenant, $attempt), secret: 'whsec_platform_test', path: '/stripe/webhook')->assertOk();

        $this->assertSame(Invoice::STATUS_ISSUED, $this->fresh($tenant, $invoice)->status);
    }

    // ── Tenancy ─────────────────────────────────────────────────────────

    public function test_tenant_isolation(): void
    {
        [$tenantA, $domainA, $invoiceA, $tokenA, $attemptA] = $this->startedCheckout();
        [$tenantB, $domainB] = $this->makeTenant(['stripe_account_id' => 'acct_seller_B']);
        [$invoiceB, $tokenB] = $this->makeInvoiceWithToken($tenantB);

        // A's token/invoice on B's domain, and B's token for A's invoice.
        $this->postJson($this->payUrl($domainB, $tokenA, $invoiceA))->assertNotFound();
        $this->postJson($this->payUrl($domainA, $tokenB, $invoiceB))->assertNotFound();

        // A paid session whose metadata points at tenant B finds nothing there.
        $this->postWebhook($this->sessionFor($tenantA, $attemptA, ['metadata' => ['tenant_id' => $tenantB->getTenantKey()]]))->assertOk();
        $this->assertSame(Invoice::STATUS_ISSUED, $this->fresh($tenantA, $invoiceA)->status);
        $this->assertSame(Invoice::STATUS_ISSUED, $this->fresh($tenantB, $invoiceB)->status);

        // Unknown tenant id -> nothing, no error.
        $this->postWebhook($this->sessionFor($tenantA, $attemptA, ['metadata' => ['tenant_id' => 'no-such-tenant']]))->assertOk();
        $this->assertSame(Invoice::STATUS_ISSUED, $this->fresh($tenantA, $invoiceA)->status);

        // The genuine event still settles A only.
        $this->postWebhook($this->sessionFor($tenantA, $attemptA))->assertOk();
        $this->assertSame(Invoice::STATUS_PAID, $this->fresh($tenantA, $invoiceA)->status);
        $this->assertSame(Invoice::STATUS_ISSUED, $this->fresh($tenantB, $invoiceB)->status);
    }

    // ── Step 6B: portal API payment capability ──────────────────────────

    private function portalInvoices(string $domain, string $token): array
    {
        return collect($this->getJson('http://' . $domain . '/api/portal/' . $token)->assertOk()->json('invoices'))
            ->keyBy('uuid')->all();
    }

    /** @return array{0: string, 1: string, 2: array<string, Invoice>} domain, token, invoices by label */
    private function customerWithInvoices(Tenant $tenant, array $variants): array
    {
        return $tenant->run(function () use ($variants) {
            $customer = Customer::factory()->create(['type' => 1]);
            $invoices = [];
            foreach ($variants as $label => $overrides) {
                $invoices[$label] = $this->makeInvoice($customer, $overrides);
            }

            return [app(ClientPortalService::class)->createAccess($customer), $invoices];
        });
    }

    public function test_the_portal_reports_which_invoices_can_be_paid_online(): void
    {
        [$tenant, $domain] = $this->makeTenant();
        [$token, $invoices] = $this->customerWithInvoices($tenant, [
            'eligible'  => [],
            'paid'      => ['status' => Invoice::STATUS_PAID, 'paid_at' => '2026-09-20 10:00:00', 'paid_via' => 'stripe'],
            'cancelled' => ['status' => Invoice::STATUS_CANCELLED],
            'zero'      => ['total' => 0, 'sub_total' => 0],
        ]);

        $payload = $this->portalInvoices($domain, $token);

        $this->assertSame(['payable' => true, 'provider' => 'stripe'], $payload[$invoices['eligible']->uuid]['payment']);
        foreach (['paid', 'cancelled', 'zero'] as $label) {
            $this->assertSame(['payable' => false, 'provider' => null], $payload[$invoices[$label]->uuid]['payment'], "$label must not be payable");
        }

        // Paid date only for a paid invoice, date only.
        $this->assertSame('2026-09-20', $payload[$invoices['paid']->uuid]['paid_at']);
        $this->assertNull($payload[$invoices['eligible']->uuid]['paid_at']);

        $this->assertCount(0, $this->stripe->requests, 'Reading the portal never talks to Stripe.');
    }

    public function test_no_invoice_is_payable_without_a_usable_connect_account_or_supported_currency(): void
    {
        foreach ([
            'no account'       => ['stripe_account_id' => null, 'onboarding_completed' => false, 'charges_enabled' => false],
            'charges disabled' => ['charges_enabled' => false],
            'onboarding todo'  => ['onboarding_completed' => false],
            'unsupported'      => ['currency' => 'JPY'],
        ] as $case => $profile) {
            [$tenant, $domain] = $this->makeTenant($profile);
            [$token, $invoices] = $this->customerWithInvoices($tenant, ['issued' => []]);

            $this->assertSame(
                ['payable' => false, 'provider' => null],
                $this->portalInvoices($domain, $token)[$invoices['issued']->uuid]['payment'],
                "$case: must not be payable"
            );
        }

        config(['services.stripe.secret' => null]);
        [$tenant, $domain] = $this->makeTenant();
        [$token, $invoices] = $this->customerWithInvoices($tenant, ['issued' => []]);
        $this->assertFalse($this->portalInvoices($domain, $token)[$invoices['issued']->uuid]['payment']['payable'], 'Stripe not configured on the platform');
    }

    public function test_the_portal_exposes_no_stripe_internals_and_stays_backward_compatible(): void
    {
        [$tenant, $domain, $invoice, $token, $attempt] = $this->startedCheckout();

        $response = $this->getJson('http://' . $domain . '/api/portal/' . $token)->assertOk();
        $raw      = $response->getContent();

        foreach ([self::SELLER_ACCOUNT, 'acct_', $attempt->uuid, $attempt->stripe_session_id, 'cs_test', 'checkout.stripe.com', 'sk_test', 'whsec_', 'stripe_account', 'attempt'] as $secret) {
            $this->assertStringNotContainsString($secret, $raw, "Portal payload must not contain [$secret]");
        }

        // Every pre-6B field is still there, unchanged in shape; only
        // paid_at and payment were added.
        $row = collect($response->json('invoices'))->firstWhere('uuid', $invoice->uuid);
        $this->assertSame(
            ['uuid', 'number', 'issue_date', 'due_date', 'total', 'status', 'currency', 'paid_at', 'payment'],
            array_keys($row)
        );
        $this->assertSame(['payable', 'provider'], array_keys($row['payment']));
        $this->assertSame(['company', 'customer', 'summary', 'invoices', 'quotes'], array_keys($response->json()));
    }

    public function test_a_just_returned_customer_sees_paid_only_after_the_webhook(): void
    {
        [$tenant, $domain, $invoice, $token, $attempt] = $this->startedCheckout();

        // Coming back with ?payment=success changes nothing server-side.
        $this->get('http://' . $domain . '/portal/' . $token . '?payment=success')->assertOk();
        $row = $this->portalInvoices($domain, $token)[$invoice->uuid];
        $this->assertSame(Invoice::STATUS_ISSUED, $row['status']);
        $this->assertTrue($row['payment']['payable']);

        $this->postWebhook($this->sessionFor($tenant, $attempt))->assertOk();

        $row = $this->portalInvoices($domain, $token)[$invoice->uuid];
        $this->assertSame(Invoice::STATUS_PAID, $row['status']);
        $this->assertSame(['payable' => false, 'provider' => null], $row['payment']);
        $this->assertNotNull($row['paid_at']);
    }
}
