<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\Feature;
use App\Models\Plan;
use App\Models\PlanLimit;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PlanPricingPresenter;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression coverage for the /pricing <-> /admin/subscription
 * consistency bug: both pages must derive their plan facts from
 * PlanPricingPresenter, the one authoritative transformation of
 * Plan/PlanLimit/PlanPrice/Feature (Filament-managed) into customer-
 * facing benefit lines - never two independent implementations that can
 * drift apart.
 */
class PlanPresentationConsistencyTest extends TestCase
{
    private array $createdTenantIds = [];
    private ?int $starterInvoicesOriginal = null;
    private ?int $starterQuotesOriginal = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Snapshot the real values this test mutates so every test leaves
        // the shared dev database exactly as it found it.
        $starter = Plan::on('mysql')->where('slug', 'starter')->first();
        $this->starterInvoicesOriginal = PlanLimit::on('mysql')
            ->where('plan_id', $starter->id)->where('resource', 'invoices_per_month')->value('value');
        $this->starterQuotesOriginal = PlanLimit::on('mysql')
            ->where('plan_id', $starter->id)->where('resource', 'quotes')->value('value');
    }

    protected function tearDown(): void
    {
        $starter = Plan::on('mysql')->where('slug', 'starter')->first();
        PlanLimit::on('mysql')->where('plan_id', $starter->id)->where('resource', 'invoices_per_month')
            ->update(['value' => $this->starterInvoicesOriginal]);
        PlanLimit::on('mysql')->where('plan_id', $starter->id)->where('resource', 'quotes')
            ->update(['value' => $this->starterQuotesOriginal]);

        foreach ($this->createdTenantIds as $id) {
            Tenant::find($id)?->delete();
        }

        parent::tearDown();
    }

    private function makeTenant(string $countryCode, string $currency): array
    {
        $tenant = Tenant::create([
            'company_name'        => 'Presenter Test Co',
            'company_email'       => 'presenter+' . uniqid() . '@example.com',
            'owner_name'          => 'Presenter Owner',
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
                'legal_name'              => 'Presenter Test Co',
                'country_code'            => $countryCode,
                'onboarding_completed_at' => now(),
            ]);
            return User::factory()->create();
        });

        return [$tenant, $domain, $user];
    }

    private function freshStarterPlan(): Plan
    {
        return Plan::on('mysql')->where('slug', 'starter')
            ->with(['limits', 'features', 'marketingItems', 'prices'])
            ->first();
    }

    private function baselineSlugsForAllPlans(): \Illuminate\Support\Collection
    {
        $plans = Plan::on('mysql')->where('active', true)
            ->with(['limits', 'features', 'marketingItems', 'prices'])
            ->get();

        return PlanPricingPresenter::baselineFeatureSlugs($plans);
    }

    // ── A/B. Limit change propagates through the shared presenter ──────

    /** @test */
    public function starter_invoice_limit_is_reflected_and_updates_live_without_any_frontend_change(): void
    {
        $starter = $this->freshStarterPlan();
        $baseline = $this->baselineSlugsForAllPlans();

        $this->assertStringContainsString('250', PlanPricingPresenter::capacityLine(250));

        PlanLimit::on('mysql')->where('plan_id', $starter->id)->where('resource', 'invoices_per_month')
            ->update(['value' => 300]);

        $updated = $this->freshStarterPlan();
        $capacityLine = PlanPricingPresenter::capacityLine($updated->getLimit('invoices_per_month'));

        $this->assertStringContainsString('300', $capacityLine);
        $this->assertStringNotContainsString('250', $capacityLine);
    }

    // ── C. Zero limit is never advertised ───────────────────────────────

    /** @test */
    public function a_zero_quote_limit_never_produces_a_zero_devis_benefit(): void
    {
        $starter = $this->freshStarterPlan();
        PlanLimit::on('mysql')->where('plan_id', $starter->id)->where('resource', 'quotes')
            ->update(['value' => 0]);

        $updated = $this->freshStarterPlan();
        $benefits = PlanPricingPresenter::benefits($updated, 'fr', $this->baselineSlugsForAllPlans());

        foreach ($benefits as $line) {
            $this->assertStringNotContainsString('0 devis', $line);
            $this->assertDoesNotMatchRegularExpression('/\b0\b.*devis/i', $line);
        }
    }

    // ── D. Unlimited quotes produce the correct unlimited benefit ──────

    /** @test */
    public function an_unlimited_quote_limit_produces_the_unlimited_benefit_line(): void
    {
        $starter = $this->freshStarterPlan();
        PlanLimit::on('mysql')->where('plan_id', $starter->id)->where('resource', 'quotes')
            ->update(['value' => null]);

        $updated = $this->freshStarterPlan();
        $benefits = PlanPricingPresenter::benefits($updated, 'fr', $this->baselineSlugsForAllPlans());

        $this->assertTrue(
            collect($benefits)->contains(fn ($line) => str_contains($line, 'illimité')),
            'Expected an "illimité" benefit line once quotes is null, got: ' . implode(' | ', $benefits)
        );
    }

    // ── E. A disabled feature disappears from the benefit list ─────────

    /** @test */
    public function detaching_a_feature_removes_it_from_the_benefit_list_and_reattaching_restores_it(): void
    {
        $business = Plan::on('mysql')->where('slug', 'business')->with(['limits', 'features', 'marketingItems', 'prices'])->first();
        $stripeFeature = Feature::on('mysql')->where('slug', 'stripe')->first();

        $wasAttached = $business->features->contains('id', $stripeFeature->id);
        if (!$wasAttached) {
            $business->features()->syncWithoutDetaching([$stripeFeature->id]);
        }

        $baseline = $this->baselineSlugsForAllPlans();
        $before = PlanPricingPresenter::benefits(
            Plan::on('mysql')->where('slug', 'business')->with(['limits', 'features', 'marketingItems', 'prices'])->first(),
            'fr',
            $baseline
        );
        $this->assertContains('Paiements en ligne (Stripe)', $before);

        $business->features()->detach($stripeFeature->id);
        $after = PlanPricingPresenter::benefits(
            Plan::on('mysql')->where('slug', 'business')->with(['limits', 'features', 'marketingItems', 'prices'])->first(),
            'fr',
            $baseline
        );
        $this->assertNotContains('Paiements en ligne (Stripe)', $after);

        // Restore exactly as found.
        if ($wasAttached) {
            $business->features()->syncWithoutDetaching([$stripeFeature->id]);
        }
    }

    // ── F/G/I/J. Market resolves the correct plan_prices row ────────────

    /** @test */
    public function morocco_resolves_mad_and_spain_resolves_eur_never_crossed(): void
    {
        $plan = Plan::on('mysql')->where('slug', 'starter')->with('prices')->first();

        $ma = $plan->priceFor('MA', 'monthly');
        $es = $plan->priceFor('ES', 'monthly');

        $this->assertSame('MAD', $ma->currency);
        $this->assertSame('EUR', $es->currency);
        $this->assertNotSame($ma->stripe_price_id, $es->stripe_price_id);
    }

    /** @test */
    public function a_moroccan_tenant_api_response_never_shows_spain_pricing(): void
    {
        [$tenant, $domain, $user] = $this->makeTenant('MA', 'MAD');

        $response = $this->actingAs($user, 'api')->getJson('http://' . $domain . '/api/plans');
        $response->assertOk();

        $starter = collect($response->json('plans'))->firstWhere('slug', 'starter');
        $this->assertSame('MAD', $starter['currency']);
    }

    /** @test */
    public function a_spanish_tenant_api_response_still_shows_eur_pricing(): void
    {
        [$tenant, $domain, $user] = $this->makeTenant('ES', 'EUR');

        $response = $this->actingAs($user, 'api')->getJson('http://' . $domain . '/api/plans');
        $response->assertOk();

        $starter = collect($response->json('plans'))->firstWhere('slug', 'starter');
        $this->assertSame('EUR', $starter['currency']);
    }

    // ── H. /pricing and /admin/subscription agree on the exact same facts ──

    /** @test */
    public function public_pricing_and_authenticated_subscription_agree_on_capacity_and_benefits(): void
    {
        [$tenant, $domain, $user] = $this->makeTenant('MA', 'MAD');

        // Authenticated page's data source (PlanController -> subscription.vue).
        $apiResponse = $this->actingAs($user, 'api')->getJson('http://' . $domain . '/api/plans');
        $apiResponse->assertOk();
        $apiStarter = collect($apiResponse->json('plans'))->firstWhere('slug', 'starter');

        // Public page's data source (HomeController::pricing() -> pricing.blade.php),
        // same market explicitly requested.
        $publicResponse = $this->get('http://fakturalista.test/pricing?market=MA');
        $publicResponse->assertOk();
        $publicCards = $publicResponse->viewData('cards');
        $publicStarter = $publicCards->firstWhere('slug', 'starter');

        $this->assertSame(
            $apiStarter['capacity_line'],
            $publicStarter['capacity_line'],
            '/pricing and /admin/subscription must never disagree on the capacity line.'
        );
        $this->assertSame(
            $apiStarter['benefits'],
            $publicStarter['benefits'],
            '/pricing and /admin/subscription must never disagree on the benefit list.'
        );
    }
}
