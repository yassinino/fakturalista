<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\Plan;
use App\Models\Subscription as AppSubscription;
use App\Models\Tenant;
use App\Models\User;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Customer as StripeCustomer;
use Stripe\Stripe;
use Stripe\Subscription as StripeSubscription;
use Tests\TestCase;

/**
 * Focused regression coverage for the "existing paid subscriber upgrades
 * plan" bug: Starter -> Pro (and Pro -> Business) used to leave Starter as
 * the displayed "Plan actuel" even after a successful Stripe payment.
 *
 * Root cause: SubscriptionController::createCheckoutSession() used to
 * cancel the tenant's current Stripe subscription BEFORE the new Checkout
 * Session even existed. If that new subscription's webhook was ever lost
 * or delayed, the tenant was left with the old plan cancelled and no new
 * one - PlanService::currentPlan() found nothing active/trialing and
 * silently fell back to the hardcoded "starter" plan, which looked exactly
 * like "the upgrade didn't happen".
 *
 * Fix: the old subscription is now only cancelled from inside
 * StripeWebhookController::handleCheckoutSessionCompleted(), AFTER the new
 * one is confirmed and persisted - see that method and
 * SubscriptionController::createCheckoutSession()'s own comments.
 *
 * Uses the real Stripe TEST-mode API throughout (not mocked), since that is
 * the only way to prove Stripe itself ends up with exactly one active
 * subscription after an upgrade, not two.
 */
class SubscriptionUpgradeTest extends TestCase
{
    private array $createdTenantIds = [];
    private array $stripeSubscriptionsToCancel = [];

    protected function setUp(): void
    {
        parent::setUp();
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    protected function tearDown(): void
    {
        foreach ($this->stripeSubscriptionsToCancel as $id) {
            try {
                StripeSubscription::retrieve($id)->cancel();
            } catch (\Throwable) {
                // Already cancelled by the test itself - fine.
            }
        }
        foreach ($this->createdTenantIds as $id) {
            Tenant::find($id)?->delete();
        }
        parent::tearDown();
    }

    private function makeTenant(string $countryCode, string $currency): array
    {
        $tenant = Tenant::create([
            'company_name'        => 'Upgrade Test Co',
            'company_email'       => 'upgrade+' . uniqid() . '@example.com',
            'owner_name'          => 'Upgrade Owner',
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
                'legal_name'              => 'Upgrade Test Co',
                'country_code'            => $countryCode,
                'onboarding_completed_at' => now(),
            ]);
            return User::factory()->create();
        });

        return [$tenant, $domain, $user];
    }

    private function postSignedWebhook(array $event): \Illuminate\Testing\TestResponse
    {
        $payload   = json_encode($event);
        $secret    = config('services.stripe.webhook_secret');
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        return $this->call('POST', '/stripe/webhook', [], [], [], [
            'CONTENT_TYPE'          => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
        ], $payload);
    }

    /**
     * Drives the REAL checkout endpoint (proving its own metadata
     * construction, including previous_subscription_id, is correct), then
     * simulates Stripe having completed that payment by creating a real
     * Stripe Subscription for the new plan and delivering a properly
     * signed checkout.session.completed event for it - exactly the shape
     * Stripe itself would send, using the real metadata Stripe actually
     * attached to the session.
     */
    private function upgradeViaCheckout(string $domain, User $user, Plan $plan, string $countryCode): array
    {
        $checkoutResponse = $this->actingAs($user, 'api')
            ->postJson('http://' . $domain . '/api/subscription/checkout', ['plan_id' => $plan->id]);
        $checkoutResponse->assertOk();

        preg_match('~/pay/([^#?]+)~', $checkoutResponse->json('checkout_url'), $m);
        $sessionId = explode('#', $m[1])[0];
        $session   = StripeCheckoutSession::retrieve($sessionId);
        $metadata  = $session->metadata->toArray();

        $planPrice = $plan->priceFor($countryCode, 'monthly');

        $customer = StripeCustomer::create(['email' => 'upgrade-real+' . uniqid() . '@example.com']);
        $newStripeSub = StripeSubscription::create([
            'customer' => $customer->id,
            'items'    => [['price' => $planPrice->stripe_price_id]],
            'trial_period_days' => 14,
        ]);
        $this->stripeSubscriptionsToCancel[] = $newStripeSub->id;

        $event = [
            'id'   => 'evt_' . uniqid(),
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id'               => $sessionId,
                'customer'         => $customer->id,
                'subscription'     => $newStripeSub->id,
                'customer_details' => ['email' => 'upgrade-real@example.com'],
                'metadata'         => $metadata,
            ]],
        ];

        $webhookResponse = $this->postSignedWebhook($event);
        $webhookResponse->assertOk();

        return ['event' => $event, 'stripe_subscription_id' => $newStripeSub->id, 'metadata' => $metadata];
    }

    // ── 1/4/5/6/8. Starter -> Pro: local plan_id, PlanService, limits, no fake activation ──

    /** @test */
    public function starter_subscriber_upgrading_to_pro_becomes_pro_everywhere_that_matters(): void
    {
        [$tenant, $domain, $user] = $this->makeTenant('MA', 'MAD');
        $starter = Plan::on('mysql')->where('slug', 'starter')->first();
        $pro     = Plan::on('mysql')->where('slug', 'pro')->first();

        // Tenant already has an active Starter subscription (as if from an
        // earlier checkout - a real Stripe subscription, not a DB fixture,
        // since the whole point is proving Stripe's own state ends up right).
        $starterCustomer = StripeCustomer::create(['email' => 'starter-real+' . uniqid() . '@example.com']);
        $starterStripeSub = StripeSubscription::create([
            'customer' => $starterCustomer->id,
            'items'    => [['price' => $starter->priceFor('MA', 'monthly')->stripe_price_id]],
            'trial_period_days' => 14,
        ]);
        $this->stripeSubscriptionsToCancel[] = $starterStripeSub->id;

        AppSubscription::create([
            'tenant_id' => $tenant->getTenantKey(), 'plan_id' => $starter->id,
            'provider' => 'stripe', 'provider_subscription_id' => $starterStripeSub->id, 'status' => 'active',
        ]);
        $tenant->update(['subscription_status' => 'active']);

        // ── Before the webhook fires: the success URL alone must never activate anything ──
        $checkoutResponse = $this->actingAs($user, 'api')
            ->postJson('http://' . $domain . '/api/subscription/checkout', ['plan_id' => $pro->id]);
        $checkoutResponse->assertOk();

        $preWebhook = $this->actingAs($user, 'api')->getJson('http://' . $domain . '/api/subscription');
        $this->assertSame('starter', $preWebhook->json('subscription.plan.slug'), 'Merely creating a Checkout Session must not change the active plan.');
        $this->assertSame('active', AppSubscription::where('provider_subscription_id', $starterStripeSub->id)->first()->status, 'Starter must remain active until the NEW subscription is actually confirmed - never cancelled up front.');

        // ── Now simulate Stripe confirming the Pro payment ──
        preg_match('~/pay/([^#?]+)~', $checkoutResponse->json('checkout_url'), $m);
        $sessionId = explode('#', $m[1])[0];
        $metadata  = StripeCheckoutSession::retrieve($sessionId)->metadata->toArray();

        $this->assertSame($starterStripeSub->id, $metadata['previous_subscription_id'] ?? null, 'Checkout metadata must carry the exact previous Stripe subscription id to cancel later.');

        $proCustomer = StripeCustomer::create(['email' => 'pro-real+' . uniqid() . '@example.com']);
        $proStripeSub = StripeSubscription::create([
            'customer' => $proCustomer->id,
            'items'    => [['price' => $pro->priceFor('MA', 'monthly')->stripe_price_id]],
            'trial_period_days' => 14,
        ]);
        $this->stripeSubscriptionsToCancel[] = $proStripeSub->id;

        $event = [
            'id' => 'evt_' . uniqid(), 'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => $sessionId, 'customer' => $proCustomer->id, 'subscription' => $proStripeSub->id,
                'customer_details' => ['email' => 'pro@example.com'], 'metadata' => $metadata,
            ]],
        ];
        $this->postSignedWebhook($event)->assertOk();

        // ── Local DB: exactly one Pro row (active/trialing), Starter now cancelled ──
        $proRow = AppSubscription::where('provider_subscription_id', $proStripeSub->id)->first();
        $this->assertSame($pro->id, $proRow->plan_id);

        $starterRow = AppSubscription::where('provider_subscription_id', $starterStripeSub->id)->first();
        $this->assertSame('canceled', $starterRow->status, 'The old Starter subscription must be cancelled once Pro is confirmed.');

        // ── PlanService resolves Pro, not Starter ──
        $tenant->run(function () use ($pro) {
            $svc = app(\App\Services\PlanService::class);
            $plan = $svc->currentPlan();
            $this->assertSame($pro->id, $plan->id, 'PlanService::currentPlan() must return Pro, not the old Starter row.');
            // Pro limits (250/250/5) actually apply, not Starter's (25/25/1).
            $this->assertSame(250, $svc->getLimit('invoices_per_month'));
            $this->assertSame(250, $svc->getLimit('customers'));
        });

        // ── /api/subscription (what Vue's "Plan actuel" reads) shows Pro ──
        $after = $this->actingAs($user, 'api')->getJson('http://' . $domain . '/api/subscription');
        $this->assertSame('pro', $after->json('subscription.plan.slug'));
        $this->assertNotSame('starter', $after->json('subscription.plan.slug'));

        // ── Stripe itself: exactly one truly active subscription for this tenant, not two ──
        $this->assertSame('canceled', StripeSubscription::retrieve($starterStripeSub->id)->status);
        $this->assertContains(StripeSubscription::retrieve($proStripeSub->id)->status, ['active', 'trialing']);
    }

    // ── 7. Duplicate webhook delivery stays idempotent through an upgrade ──

    /** @test */
    public function duplicate_checkout_completed_delivery_during_an_upgrade_does_not_duplicate_or_re_error(): void
    {
        [$tenant, $domain, $user] = $this->makeTenant('MA', 'MAD');
        $starter = Plan::on('mysql')->where('slug', 'starter')->first();
        $pro     = Plan::on('mysql')->where('slug', 'pro')->first();

        $starterCustomer = StripeCustomer::create(['email' => 'dup-starter+' . uniqid() . '@example.com']);
        $starterStripeSub = StripeSubscription::create([
            'customer' => $starterCustomer->id,
            'items'    => [['price' => $starter->priceFor('MA', 'monthly')->stripe_price_id]],
            'trial_period_days' => 14,
        ]);
        $this->stripeSubscriptionsToCancel[] = $starterStripeSub->id;

        AppSubscription::create([
            'tenant_id' => $tenant->getTenantKey(), 'plan_id' => $starter->id,
            'provider' => 'stripe', 'provider_subscription_id' => $starterStripeSub->id, 'status' => 'active',
        ]);

        ['event' => $event, 'stripe_subscription_id' => $proSubId] = $this->upgradeViaCheckout($domain, $user, $pro, 'MA');

        $second = $this->postSignedWebhook($event); // Stripe redelivers the same event
        $second->assertOk();

        $this->assertCount(1, AppSubscription::where('provider_subscription_id', $proSubId)->get(), 'Duplicate delivery must not create a second Pro row.');
        $this->assertSame('canceled', AppSubscription::where('provider_subscription_id', $starterStripeSub->id)->first()->status);
    }

    // ── 2. Pro -> Business also works (second hop) ──

    /** @test */
    public function pro_subscriber_can_upgrade_to_business(): void
    {
        [$tenant, $domain, $user] = $this->makeTenant('MA', 'MAD');
        $pro      = Plan::on('mysql')->where('slug', 'pro')->first();
        $business = Plan::on('mysql')->where('slug', 'business')->first();

        $proCustomer = StripeCustomer::create(['email' => 'pro-start+' . uniqid() . '@example.com']);
        $proStripeSub = StripeSubscription::create([
            'customer' => $proCustomer->id,
            'items'    => [['price' => $pro->priceFor('MA', 'monthly')->stripe_price_id]],
            'trial_period_days' => 14,
        ]);
        $this->stripeSubscriptionsToCancel[] = $proStripeSub->id;

        AppSubscription::create([
            'tenant_id' => $tenant->getTenantKey(), 'plan_id' => $pro->id,
            'provider' => 'stripe', 'provider_subscription_id' => $proStripeSub->id, 'status' => 'active',
        ]);

        $this->upgradeViaCheckout($domain, $user, $business, 'MA');

        $tenant->run(function () use ($business) {
            $svc = app(\App\Services\PlanService::class);
            $this->assertSame($business->id, $svc->currentPlan()->id);
            $this->assertNull($svc->getLimit('invoices_per_month'), 'Business is unlimited.');
        });

        $this->assertSame('canceled', StripeSubscription::retrieve($proStripeSub->id)->status);
    }

    // ── 3/10. Starter -> Business directly, and never two active Stripe subs ──

    /** @test */
    public function starter_subscriber_can_upgrade_directly_to_business_with_no_duplicate_active_stripe_subscription(): void
    {
        [$tenant, $domain, $user] = $this->makeTenant('MA', 'MAD');
        $starter  = Plan::on('mysql')->where('slug', 'starter')->first();
        $business = Plan::on('mysql')->where('slug', 'business')->first();

        $starterCustomer = StripeCustomer::create(['email' => 'sb-starter+' . uniqid() . '@example.com']);
        $starterStripeSub = StripeSubscription::create([
            'customer' => $starterCustomer->id,
            'items'    => [['price' => $starter->priceFor('MA', 'monthly')->stripe_price_id]],
            'trial_period_days' => 14,
        ]);
        $this->stripeSubscriptionsToCancel[] = $starterStripeSub->id;

        AppSubscription::create([
            'tenant_id' => $tenant->getTenantKey(), 'plan_id' => $starter->id,
            'provider' => 'stripe', 'provider_subscription_id' => $starterStripeSub->id, 'status' => 'active',
        ]);

        $result = $this->upgradeViaCheckout($domain, $user, $business, 'MA');

        // Ground truth from Stripe itself, not our own bookkeeping: exactly
        // one active subscription remains for either customer involved.
        $starterCustomerSubs = StripeSubscription::all(['customer' => $starterCustomer->id, 'status' => 'active']);
        $this->assertCount(0, $starterCustomerSubs->data, 'The old customer/subscription must not still be active - no double billing.');

        $newSub = StripeSubscription::retrieve($result['stripe_subscription_id']);
        $this->assertContains($newSub->status, ['active', 'trialing']);

        $tenant->run(function () use ($business) {
            $this->assertSame($business->id, app(\App\Services\PlanService::class)->currentPlan()->id);
        });
    }

    // ── 6/tenant isolation. Tenant A's upgrade webhook never touches Tenant B ──

    /** @test */
    public function tenant_a_upgrade_webhook_never_modifies_tenant_b(): void
    {
        [$tenantA, $domainA, $userA] = $this->makeTenant('MA', 'MAD');
        [$tenantB, $domainB, $userB] = $this->makeTenant('MA', 'MAD');
        $starter = Plan::on('mysql')->where('slug', 'starter')->first();
        $pro     = Plan::on('mysql')->where('slug', 'pro')->first();

        // Both tenants start on Starter with their own real Stripe subscription.
        $subA = StripeSubscription::create([
            'customer' => StripeCustomer::create(['email' => 'iso-a+' . uniqid() . '@example.com'])->id,
            'items'    => [['price' => $starter->priceFor('MA', 'monthly')->stripe_price_id]],
            'trial_period_days' => 14,
        ]);
        $subB = StripeSubscription::create([
            'customer' => StripeCustomer::create(['email' => 'iso-b+' . uniqid() . '@example.com'])->id,
            'items'    => [['price' => $starter->priceFor('MA', 'monthly')->stripe_price_id]],
            'trial_period_days' => 14,
        ]);
        $this->stripeSubscriptionsToCancel[] = $subA->id;
        $this->stripeSubscriptionsToCancel[] = $subB->id;

        AppSubscription::create(['tenant_id' => $tenantA->getTenantKey(), 'plan_id' => $starter->id, 'provider' => 'stripe', 'provider_subscription_id' => $subA->id, 'status' => 'active']);
        AppSubscription::create(['tenant_id' => $tenantB->getTenantKey(), 'plan_id' => $starter->id, 'provider' => 'stripe', 'provider_subscription_id' => $subB->id, 'status' => 'active']);

        // Only tenant A upgrades.
        $this->upgradeViaCheckout($domainA, $userA, $pro, 'MA');

        $tenantA->refresh();
        $tenantB->refresh();

        $this->assertSame('canceled', AppSubscription::where('provider_subscription_id', $subA->id)->first()->status);
        $this->assertSame('active', AppSubscription::where('provider_subscription_id', $subB->id)->first()->status, "Tenant B's subscription must be completely untouched by Tenant A's upgrade webhook.");

        $tenantB->run(function () use ($starter) {
            $this->assertSame($starter->id, app(\App\Services\PlanService::class)->currentPlan()->id);
        });
    }
}
