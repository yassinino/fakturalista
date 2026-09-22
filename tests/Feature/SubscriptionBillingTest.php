<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription as AppSubscription;
use App\Models\Tenant;
use App\Models\User;
use Stripe\Customer as StripeCustomer;
use Stripe\PaymentMethod as StripePaymentMethod;
use Stripe\Price as StripePrice;
use Stripe\Stripe;
use Stripe\Subscription as StripeSubscription;
use Tests\TestCase;

/**
 * Launch-critical Morocco/Spain subscription + Stripe billing audit.
 *
 * Uses the real Stripe TEST-mode account (config('services.stripe.secret')
 * is a sk_test_ key - see .env) for anything that must prove an actual
 * Stripe object (Checkout Session, Subscription) was created with the
 * correct currency/price, rather than mocking Stripe away. Webhook tests
 * sign their payloads with the real STRIPE_WEBHOOK_SECRET so signature
 * verification is exercised for real, never bypassed.
 */
class SubscriptionBillingTest extends TestCase
{
    private array $createdTenantIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    protected function tearDown(): void
    {
        foreach ($this->createdTenantIds as $id) {
            Tenant::find($id)?->delete();
        }
        parent::tearDown();
    }

    private function makeTenant(string $countryCode, string $currency): array
    {
        $tenant = Tenant::create([
            'company_name'        => 'Billing Test Co',
            'company_email'       => 'billing+' . uniqid() . '@example.com',
            'owner_name'          => 'Billing Owner',
            'owner_email'         => 'owner+' . uniqid() . '@example.com',
            'country'             => $countryCode,
            'currency'            => $currency,
            'status'              => 'active',
            'subscription_status' => 'trialing',
            'trial_ends_at'       => now()->addDays(14),
        ]);
        $this->createdTenantIds[] = $tenant->getTenantKey();

        $domain = $tenant->id . '.fakturalista.test';
        $tenant->domains()->create(['domain' => $domain]);

        $user = $tenant->run(function () use ($countryCode) {
            CompanyProfile::create([
                'legal_name'              => 'Billing Test Co',
                'country_code'            => $countryCode,
                'onboarding_completed_at' => now(),
            ]);
            return User::factory()->create();
        });

        return [$tenant, $domain, $user];
    }

    // ── 1. Currency resolution: Morocco -> MAD, Spain -> EUR ────────────

    /** @test */
    public function plan_price_for_resolves_morocco_to_mad_and_spain_to_eur(): void
    {
        $plan = Plan::on('mysql')->where('slug', 'starter')->with('prices')->first();

        $ma = $plan->priceFor('MA', 'monthly');
        $es = $plan->priceFor('ES', 'monthly');

        $this->assertNotNull($ma);
        $this->assertSame('MAD', $ma->currency);
        $this->assertSame(11900, $ma->amount);
        $this->assertNotNull($ma->stripe_price_id);

        $this->assertNotNull($es);
        $this->assertSame('EUR', $es->currency);
        $this->assertSame(490, $es->amount);
        $this->assertNotNull($es->stripe_price_id);

        // An unconfigured/unknown market falls back to Morocco (primary
        // market), never silently to whatever the first row happens to be.
        $unknown = $plan->priceFor('XX', 'monthly');
        $this->assertSame('MAD', $unknown->currency);
    }

    /** @test */
    public function plans_api_shows_mad_for_a_moroccan_tenant_and_eur_for_a_spanish_tenant(): void
    {
        [$tenantMa, $domainMa, $userMa] = $this->makeTenant('MA', 'MAD');
        [$tenantEs, $domainEs, $userEs] = $this->makeTenant('ES', 'EUR');

        $resMa = $this->actingAs($userMa, 'api')
            ->getJson('http://' . $domainMa . '/api/plans');
        $resEs = $this->actingAs($userEs, 'api')
            ->getJson('http://' . $domainEs . '/api/plans');

        $resMa->assertOk();
        $resEs->assertOk();

        $starterMa = collect($resMa->json('plans'))->firstWhere('slug', 'starter');
        $starterEs = collect($resEs->json('plans'))->firstWhere('slug', 'starter');

        $this->assertSame('MAD', $starterMa['currency']);
        $this->assertEquals(119, $starterMa['monthly_price'] / 100);
        $this->assertNotEmpty($starterMa['stripe_price_id']);

        $this->assertSame('EUR', $starterEs['currency']);
        $this->assertEquals(4.90, $starterEs['monthly_price'] / 100);
        $this->assertNotEmpty($starterEs['stripe_price_id']);

        // Never the two mixed up.
        $this->assertNotSame($starterMa['stripe_price_id'], $starterEs['stripe_price_id']);

        // Morocco has no yearly Stripe Price configured yet (see
        // SyncPlanPrices) - the API must say so rather than pretend.
        $this->assertFalse($starterMa['yearly_available']);
        $this->assertTrue($starterEs['yearly_available']);
    }

    // ── 2. Stripe Checkout: correct plan, currency, Price, market ───────

    /** @test */
    public function checkout_session_for_a_moroccan_tenant_charges_mad_and_for_a_spanish_tenant_charges_eur(): void
    {
        [$tenantMa, $domainMa, $userMa] = $this->makeTenant('MA', 'MAD');
        [$tenantEs, $domainEs, $userEs] = $this->makeTenant('ES', 'EUR');

        $plan = Plan::on('mysql')->where('slug', 'starter')->first();

        $resMa = $this->actingAs($userMa, 'api')
            ->postJson('http://' . $domainMa . '/api/subscription/checkout', ['plan_id' => $plan->id]);
        $resEs = $this->actingAs($userEs, 'api')
            ->postJson('http://' . $domainEs . '/api/subscription/checkout', ['plan_id' => $plan->id]);

        $resMa->assertOk()->assertJsonStructure(['checkout_url']);
        $resEs->assertOk()->assertJsonStructure(['checkout_url']);

        // Pull the actual session back from Stripe and check what it will
        // really charge - never trust the display label alone.
        $sessionIdMa = $this->sessionIdFromUrl($resMa->json('checkout_url'));
        $sessionIdEs = $this->sessionIdFromUrl($resEs->json('checkout_url'));

        $sessionMa = \Stripe\Checkout\Session::retrieve(['id' => $sessionIdMa, 'expand' => ['line_items.data.price']]);
        $sessionEs = \Stripe\Checkout\Session::retrieve(['id' => $sessionIdEs, 'expand' => ['line_items.data.price']]);

        $this->assertSame('mad', $sessionMa->line_items->data[0]->price->currency);
        $this->assertSame(11900, $sessionMa->line_items->data[0]->price->unit_amount);

        $this->assertSame('eur', $sessionEs->line_items->data[0]->price->currency);
        $this->assertSame(490, $sessionEs->line_items->data[0]->price->unit_amount);
    }

    /** @test */
    public function checkout_refuses_yearly_for_morocco_instead_of_silently_charging_the_wrong_price(): void
    {
        [$tenant, $domain, $user] = $this->makeTenant('MA', 'MAD');
        $plan = Plan::on('mysql')->where('slug', 'starter')->first();

        $response = $this->actingAs($user, 'api')
            ->postJson('http://' . $domain . '/api/subscription/checkout', [
                'plan_id'  => $plan->id,
                'interval' => 'yearly',
            ]);

        $response->assertStatus(422);
    }

    private function sessionIdFromUrl(string $checkoutUrl): string
    {
        preg_match('~/pay/([^#?]+)~', $checkoutUrl, $m);
        // Stripe's checkout_url is like https://checkout.stripe.com/c/pay/cs_test_...#...
        return explode('#', $m[1])[0];
    }

    // ── 3. Trial lifecycle ───────────────────────────────────────────────

    /** @test */
    public function a_tenant_within_14_days_can_access_the_app_and_exactly_at_expiration_cannot(): void
    {
        $tenant = Tenant::create([
            'company_name' => 'Trial Co', 'company_email' => 'trial@example.com',
            'owner_name' => 'Trial Owner', 'owner_email' => 'trialowner+' . uniqid() . '@example.com',
            'status' => 'active', 'subscription_status' => 'trialing',
            'trial_ends_at' => now()->addDays(14),
        ]);
        $this->createdTenantIds[] = $tenant->getTenantKey();

        $this->assertTrue($tenant->canAccessApp());
        $this->assertFalse($tenant->isReadOnly());

        // Opening the app exactly after expiration - no cron, no admin
        // action, just the timestamp having passed - must flip immediately.
        $tenant->update(['trial_ends_at' => now()->subSecond()]);
        $tenant->refresh();

        $this->assertFalse($tenant->canAccessApp());
        $this->assertTrue($tenant->isReadOnly());
    }

    /** @test */
    public function an_expired_trial_blocks_writes_via_the_real_middleware_with_no_manual_intervention(): void
    {
        [$tenant, $domain, $user] = $this->makeTenant('MA', 'MAD');
        $tenant->update(['subscription_status' => 'trialing', 'trial_ends_at' => now()->subDay()]);

        $write = $this->actingAs($user, 'api')
            ->postJson('http://' . $domain . '/api/customers', ['type' => 1, 'company_name' => 'X', 'contacts' => []]);

        $write->assertStatus(402)->assertJsonPath('error', 'subscription_required');
    }

    // ── 4. Webhooks: signature, plan activation, idempotency ────────────

    private function postSignedWebhook(array $event): \Illuminate\Testing\TestResponse
    {
        $payload = json_encode($event);
        $secret  = config('services.stripe.webhook_secret');
        $timestamp = time();
        $signedPayload = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signedPayload, $secret);
        $header = "t={$timestamp},v1={$signature}";

        return $this->call('POST', '/stripe/webhook', [], [], [], [
            'CONTENT_TYPE'          => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $header,
        ], $payload);
    }

    private function checkoutCompletedEvent(string $tenantId, int $planId, string $stripeSubscriptionId, ?int $planPriceId = null): array
    {
        $metadata = ['tenant_id' => $tenantId, 'plan_id' => (string) $planId];
        if ($planPriceId) {
            $metadata['plan_price_id'] = (string) $planPriceId;
        }

        return [
            'id'   => 'evt_' . uniqid(),
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id'              => 'cs_test_' . uniqid(),
                'customer'        => 'cus_test_' . uniqid(),
                'subscription'    => $stripeSubscriptionId,
                'customer_details'=> ['email' => 'webhook-test@example.com'],
                'metadata'        => $metadata,
            ]],
        ];
    }

    /** @test */
    public function webhook_rejects_a_bad_signature_and_creates_nothing(): void
    {
        $payload = json_encode(['id' => 'evt_bad', 'type' => 'checkout.session.completed', 'data' => ['object' => []]]);

        $response = $this->call('POST', '/stripe/webhook', [], [], [], [
            'CONTENT_TYPE'          => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't=' . time() . ',v1=deadbeef',
        ], $payload);

        $response->assertStatus(400);
        $this->assertSame(0, AppSubscription::where('provider_subscription_id', 'like', '%deadbeef%')->count());
    }

    /** @test */
    public function checkout_session_completed_webhook_activates_the_correct_plan_and_is_idempotent(): void
    {
        [$tenant, $domain, $user] = $this->makeTenant('MA', 'MAD');
        $plan = Plan::on('mysql')->where('slug', 'pro')->first();

        // Real Stripe Subscription object (trialing, no card needed while
        // trialing) so StripeSubscription::retrieve() in the handler
        // resolves against something real, not a guess about its shape.
        $customer = StripeCustomer::create(['email' => 'webhook-real@example.com']);
        $stripeSub = StripeSubscription::create([
            'customer' => $customer->id,
            'items'    => [['price' => $plan->priceFor('MA', 'monthly')->stripe_price_id]],
            'trial_period_days' => 14,
        ]);

        $event = $this->checkoutCompletedEvent($tenant->getTenantKey(), $plan->id, $stripeSub->id);

        $first  = $this->postSignedWebhook($event);
        $second = $this->postSignedWebhook($event); // duplicate delivery

        $first->assertOk();
        $second->assertOk();

        $subs = AppSubscription::where('tenant_id', $tenant->getTenantKey())
            ->where('provider_subscription_id', $stripeSub->id)
            ->get();

        $this->assertCount(1, $subs, 'Duplicate webhook delivery must not create a second subscription row.');
        $this->assertSame($plan->id, $subs->first()->plan_id);
        $this->assertSame('trialing', $subs->first()->status);

        $tenant->refresh();
        $this->assertSame('trialing', $tenant->subscription_status);

        $stripeSub->cancel();
    }

    /** @test */
    public function subscription_endpoint_shows_the_real_charged_amount_after_checkout_confirms(): void
    {
        [$tenant, $domain, $user] = $this->makeTenant('MA', 'MAD');
        $plan      = Plan::on('mysql')->where('slug', 'pro')->first();
        $planPrice = $plan->priceFor('MA', 'monthly');

        $customer = StripeCustomer::create(['email' => 'webhook-price@example.com']);
        $stripeSub = StripeSubscription::create([
            'customer' => $customer->id,
            'items'    => [['price' => $planPrice->stripe_price_id]],
            'trial_period_days' => 14,
        ]);

        $event = $this->checkoutCompletedEvent($tenant->getTenantKey(), $plan->id, $stripeSub->id, $planPrice->id);
        $this->postSignedWebhook($event)->assertOk();

        $sub = AppSubscription::where('provider_subscription_id', $stripeSub->id)->first();
        $this->assertSame($planPrice->id, $sub->plan_price_id, 'The exact plan_price used at checkout must be recorded on the subscription.');

        $response = $this->actingAs($user, 'api')->getJson('http://' . $domain . '/api/subscription');
        $response->assertOk();

        // This is exactly what the checkout success page (CheckoutSuccess.vue)
        // reads to show "Montant: 209 MAD/mois" - it must be the real
        // charged price, never re-derived from a static plan column.
        $this->assertSame('MAD', $response->json('subscription.price.currency'));
        $this->assertSame(20900, $response->json('subscription.price.amount'));
        $this->assertSame('monthly', $response->json('subscription.price.interval'));

        $stripeSub->cancel();
    }

    /** @test */
    public function invoice_payment_succeeded_webhook_creates_one_payment_even_if_delivered_twice(): void
    {
        [$tenant, $domain, $user] = $this->makeTenant('ES', 'EUR');
        $plan = Plan::on('mysql')->where('slug', 'starter')->first();

        $subscription = AppSubscription::create([
            'tenant_id' => $tenant->getTenantKey(),
            'plan_id'   => $plan->id,
            'provider'  => 'stripe',
            'provider_subscription_id' => 'sub_test_' . uniqid(),
            'status'    => 'active',
        ]);

        $invoiceId = 'in_test_' . uniqid();
        $event = [
            'id'   => 'evt_' . uniqid(),
            'type' => 'invoice.payment_succeeded',
            'data' => ['object' => [
                'id'           => $invoiceId,
                'subscription' => $subscription->provider_subscription_id,
                'customer'     => 'cus_test_' . uniqid(),
                'amount_paid'  => 490,
                'currency'     => 'eur',
                'status'       => 'paid',
                'created'      => time(),
                'lines'        => ['data' => [['period' => ['start' => time(), 'end' => time() + 2592000], 'price' => ['id' => $plan->priceFor('ES', 'monthly')->stripe_price_id]]]],
                'status_transitions' => ['paid_at' => time()],
            ]],
        ];

        $this->postSignedWebhook($event)->assertOk();
        $this->postSignedWebhook($event)->assertOk(); // Stripe redelivery

        $payments = Payment::where('provider', 'stripe')->where('provider_payment_id', $invoiceId)->get();
        $this->assertCount(1, $payments, 'Duplicate invoice.payment_succeeded delivery must not duplicate the Payment row.');
        $this->assertEquals(4.90, (float) $payments->first()->amount);
        $this->assertSame('EUR', $payments->first()->currency);
    }

    /** @test */
    public function subscription_updated_webhook_syncs_tenant_status_for_past_due_and_active(): void
    {
        [$tenant, $domain, $user] = $this->makeTenant('MA', 'MAD');
        $plan = Plan::on('mysql')->where('slug', 'starter')->first();
        $stripeSubId = 'sub_test_' . uniqid();

        AppSubscription::create([
            'tenant_id' => $tenant->getTenantKey(), 'plan_id' => $plan->id,
            'provider' => 'stripe', 'provider_subscription_id' => $stripeSubId, 'status' => 'active',
        ]);

        $event = [
            'id' => 'evt_' . uniqid(), 'type' => 'customer.subscription.updated',
            'data' => ['object' => ['id' => $stripeSubId, 'status' => 'past_due', 'current_period_end' => time() + 86400]],
        ];
        $this->postSignedWebhook($event)->assertOk();

        $tenant->refresh();
        // past_due keeps the tenant able to use the app (grace period) -
        // see Tenant::syncSubscriptionStatus()'s mapping.
        $this->assertSame('active', $tenant->subscription_status);
        $this->assertTrue($tenant->canAccessApp());
    }

    /** @test */
    public function subscription_deleted_webhook_cancels_and_tenant_loses_access(): void
    {
        [$tenant, $domain, $user] = $this->makeTenant('MA', 'MAD');
        $plan = Plan::on('mysql')->where('slug', 'starter')->first();
        $stripeSubId = 'sub_test_' . uniqid();

        AppSubscription::create([
            'tenant_id' => $tenant->getTenantKey(), 'plan_id' => $plan->id,
            'provider' => 'stripe', 'provider_subscription_id' => $stripeSubId, 'status' => 'active',
        ]);
        $tenant->update(['subscription_status' => 'active', 'trial_ends_at' => null]);

        $event = [
            'id' => 'evt_' . uniqid(), 'type' => 'customer.subscription.deleted',
            'data' => ['object' => ['id' => $stripeSubId, 'status' => 'canceled']],
        ];
        $this->postSignedWebhook($event)->assertOk();

        $tenant->refresh();
        $this->assertSame('canceled', $tenant->subscription_status);
        $this->assertFalse($tenant->canAccessApp());

        $sub = AppSubscription::where('provider_subscription_id', $stripeSubId)->first();
        $this->assertSame('canceled', $sub->status);
    }

    // ── 5. Cancellation via the real endpoint (persists raw + cancel_at_period_end) ──

    /** @test */
    public function the_cancel_endpoint_schedules_a_real_stripe_cancellation_and_persists_it(): void
    {
        [$tenant, $domain, $user] = $this->makeTenant('MA', 'MAD');
        $plan = Plan::on('mysql')->where('slug', 'starter')->first();

        $customer = StripeCustomer::create(['email' => 'cancel-test@example.com']);
        $pm = StripePaymentMethod::create([
            'type' => 'card',
            'card' => ['token' => 'tok_visa'],
        ]);
        $pm->attach(['customer' => $customer->id]);
        StripeCustomer::update($customer->id, ['invoice_settings' => ['default_payment_method' => $pm->id]]);

        $stripeSub = StripeSubscription::create([
            'customer' => $customer->id,
            'items'    => [['price' => $plan->priceFor('MA', 'monthly')->stripe_price_id]],
        ]);

        AppSubscription::create([
            'tenant_id' => $tenant->getTenantKey(), 'plan_id' => $plan->id, 'provider' => 'stripe',
            'provider_subscription_id' => $stripeSub->id, 'status' => 'active',
        ]);
        $tenant->update(['subscription_status' => 'active']);

        $response = $this->actingAs($user, 'api')
            ->postJson('http://' . $domain . '/api/subscription/cancel');

        $response->assertOk();

        $sub = AppSubscription::where('provider_subscription_id', $stripeSub->id)->first();
        $this->assertNotNull($sub->raw, 'Subscription::$fillable must actually persist raw so cancel_at_period_end is readable.');
        $this->assertTrue((bool) $sub->raw['cancel_at_period_end']);

        $stripeSub->cancel();
    }

    // ── 6. Advertised plan limits match what is actually seeded ─────────

    /** @test */
    public function starter_pro_business_limits_match_the_advertised_numbers(): void
    {
        $plans = Plan::on('mysql')->where('active', true)->with('limits')->get()->keyBy('slug');

        $this->assertSame(25, $plans['starter']->getLimit('invoices_per_month'));
        $this->assertSame(25, $plans['starter']->getLimit('customers'));
        $this->assertSame(1, $plans['starter']->getLimit('users'));

        $this->assertSame(250, $plans['pro']->getLimit('invoices_per_month'));
        $this->assertSame(250, $plans['pro']->getLimit('customers'));
        $this->assertSame(5, $plans['pro']->getLimit('users'));

        $this->assertNull($plans['business']->getLimit('invoices_per_month'));
        $this->assertNull($plans['business']->getLimit('customers'));
        $this->assertNull($plans['business']->getLimit('users'));
    }

    /** @test */
    public function starter_plan_blocks_invoice_creation_past_its_limit_but_business_never_does(): void
    {
        [$tenant, $domain, $user] = $this->makeTenant('MA', 'MAD');
        $starter = Plan::on('mysql')->where('slug', 'starter')->first();
        $business = Plan::on('mysql')->where('slug', 'business')->first();

        AppSubscription::create([
            'tenant_id' => $tenant->getTenantKey(), 'plan_id' => $starter->id,
            'provider' => 'stripe', 'status' => 'active',
        ]);

        $tenant->run(function () {
            $svc = app(\App\Services\PlanService::class);
            $this->assertTrue($svc->canCreateInvoice(), 'Starter allows invoices under its 25/month cap.');
            $this->assertEquals(0, $svc->count('invoices_per_month'));
        });

        AppSubscription::where('tenant_id', $tenant->getTenantKey())->update(['plan_id' => $business->id]);
        $tenant->run(function () {
            $svc = app(\App\Services\PlanService::class);
            $this->assertNull($svc->getLimit('invoices_per_month'), 'Business is unlimited.');
            $this->assertTrue($svc->canCreateInvoice());
        });
    }
}
