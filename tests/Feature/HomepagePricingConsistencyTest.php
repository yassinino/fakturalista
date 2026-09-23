<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\PlanLimit;
use Tests\TestCase;

/**
 * Regression coverage for the homepage-pricing-section <-> /pricing
 * consistency bug: the homepage used to read Plan's legacy static
 * monthly_price/currency columns (Spain-only, pre-plan_prices) directly,
 * while /pricing already read plan_prices via PlanPricingPresenter. Both
 * pages must now build their cards from the exact same
 * HomeController::index()/pricing() -> PlanPricingPresenter::present()
 * pipeline - never two independent presentations that can drift apart.
 */
class HomepagePricingConsistencyTest extends TestCase
{
    private ?int $starterInvoicesOriginal = null;

    protected function setUp(): void
    {
        parent::setUp();

        $starter = Plan::on('mysql')->where('slug', 'starter')->first();
        $this->starterInvoicesOriginal = PlanLimit::on('mysql')
            ->where('plan_id', $starter->id)->where('resource', 'invoices_per_month')->value('value');
    }

    protected function tearDown(): void
    {
        $starter = Plan::on('mysql')->where('slug', 'starter')->first();
        PlanLimit::on('mysql')->where('plan_id', $starter->id)->where('resource', 'invoices_per_month')
            ->update(['value' => $this->starterInvoicesOriginal]);

        parent::tearDown();
    }

    private function homeCards(string $market): \Illuminate\Support\Collection
    {
        $response = $this->get('http://fakturalista.test/?market=' . $market);
        $response->assertOk();
        return $response->viewData('cards');
    }

    private function pricingCards(string $market): \Illuminate\Support\Collection
    {
        $response = $this->get('http://fakturalista.test/pricing?market=' . $market);
        $response->assertOk();
        return $response->viewData('cards');
    }

    /** @test */
    public function morocco_homepage_shows_the_real_mad_prices_matching_pricing_page(): void
    {
        $home = $this->homeCards('MA');
        $pricing = $this->pricingCards('MA');

        $this->assertSame('119', $home->firstWhere('slug', 'starter')['price']);
        $this->assertSame('209', $home->firstWhere('slug', 'pro')['price']);
        $this->assertSame('799', $home->firstWhere('slug', 'business')['price']);

        foreach (['starter', 'pro', 'business'] as $slug) {
            $this->assertSame(
                $pricing->firstWhere('slug', $slug)['price'],
                $home->firstWhere('slug', $slug)['price'],
                "Homepage and /pricing must agree on {$slug}'s price."
            );
            $this->assertSame(
                $pricing->firstWhere('slug', $slug)['currency'],
                $home->firstWhere('slug', $slug)['currency']
            );
        }
    }

    /** @test */
    public function homepage_and_pricing_agree_on_capacity_and_benefits_for_every_plan(): void
    {
        $home = $this->homeCards('MA');
        $pricing = $this->pricingCards('MA');

        foreach (['starter', 'pro', 'business'] as $slug) {
            $homeCard = $home->firstWhere('slug', $slug);
            $pricingCard = $pricing->firstWhere('slug', $slug);

            $this->assertSame($pricingCard['capacity_line'], $homeCard['capacity_line']);
            $this->assertSame($pricingCard['benefits'], $homeCard['benefits']);
            $this->assertSame($pricingCard['trial_days'], $homeCard['trial_days']);
        }
    }

    /** @test */
    public function homepage_never_shows_the_old_static_spain_only_price_under_morocco_market(): void
    {
        $home = $this->homeCards('MA');

        foreach ($home as $card) {
            $this->assertNotSame('4,90', $card['price']);
            $this->assertNotSame('9,90', $card['price']);
            $this->assertNotSame('19,90', $card['price']);
        }
    }

    /** @test */
    public function spain_market_still_resolves_eur_pricing_on_both_pages(): void
    {
        $home = $this->homeCards('ES');
        $pricing = $this->pricingCards('ES');

        $this->assertSame('4,90', $home->firstWhere('slug', 'starter')['price']);
        $this->assertSame('€', $home->firstWhere('slug', 'starter')['currency']);

        foreach (['starter', 'pro', 'business'] as $slug) {
            $this->assertSame($pricing->firstWhere('slug', $slug)['price'], $home->firstWhere('slug', $slug)['price']);
        }
    }

    /** @test */
    public function a_filament_style_plan_limit_change_propagates_to_both_pages_without_any_code_change(): void
    {
        $starter = Plan::on('mysql')->where('slug', 'starter')->first();

        // Simulate an admin edit in Filament.
        PlanLimit::on('mysql')->where('plan_id', $starter->id)->where('resource', 'invoices_per_month')
            ->update(['value' => 321]);

        $home = $this->homeCards('MA');
        $pricing = $this->pricingCards('MA');

        $this->assertStringContainsString('321', $home->firstWhere('slug', 'starter')['capacity_line']);
        $this->assertStringContainsString('321', $pricing->firstWhere('slug', 'starter')['capacity_line']);
        $this->assertSame(
            $pricing->firstWhere('slug', 'starter')['capacity_line'],
            $home->firstWhere('slug', 'starter')['capacity_line']
        );
    }
}
