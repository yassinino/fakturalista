<?php

namespace App\Console\Commands;

use App\Models\Plan;
use App\Models\PlanPrice;
use Illuminate\Console\Command;
use Stripe\Price as StripePrice;
use Stripe\Product as StripeProduct;
use Stripe\Stripe;

/**
 * Seeds/repairs `plan_prices` (Starter/Pro/Business x Morocco-MAD /
 * Spain-EUR) and creates the matching Stripe Product/Price objects
 * (test mode - config('services.stripe.secret')) for any row still
 * missing a stripe_price_id.
 *
 * Idempotent: a plan_prices row that already has a stripe_price_id is
 * left untouched and no new Stripe object is created for it - safe to
 * re-run after adding a new market/plan.
 *
 * Prices below are the real, explicitly-decided launch prices (Morocco
 * MAD figures given directly by the business owner; Spain EUR figures
 * already live in `plans.monthly_price`/`yearly_price`) - never invented
 * or currency-converted.
 */
class SyncPlanPrices extends Command
{
    protected $signature = 'plans:sync-prices {--dry-run : Only print what would change, create nothing in Stripe}';

    protected $description = 'Seed plan_prices (Morocco MAD / Spain EUR) and create matching Stripe Prices for any row missing one';

    /**
     * @var array<string, array{monthly:int, yearly:?int}> amounts in minor units (cents)
     */
    private const MOROCCO_MAD = [
        'starter'  => ['monthly' => 11900, 'yearly' => null],
        'pro'      => ['monthly' => 20900, 'yearly' => null],
        'business' => ['monthly' => 79900, 'yearly' => null],
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        Stripe::setApiKey(config('services.stripe.secret'));

        $plans = Plan::on('mysql')->where('active', true)->get()->keyBy('slug');
        $productCache = [];

        foreach ($plans as $slug => $plan) {
            // ── Spain (EUR) - reuses the amounts already on the plan row ──
            $this->ensurePrice($plan, 'ES', 'EUR', 'monthly', $plan->monthly_price, $dryRun, $productCache);
            if ($plan->yearly_price) {
                $this->ensurePrice($plan, 'ES', 'EUR', 'yearly', $plan->yearly_price, $dryRun, $productCache);
            }

            // ── Morocco (MAD) - explicit launch prices, monthly only for now ──
            $ma = self::MOROCCO_MAD[$slug] ?? null;
            if ($ma) {
                $this->ensurePrice($plan, 'MA', 'MAD', 'monthly', $ma['monthly'], $dryRun, $productCache);
                if ($ma['yearly']) {
                    $this->ensurePrice($plan, 'MA', 'MAD', 'yearly', $ma['yearly'], $dryRun, $productCache);
                }
            }
        }

        $this->info($dryRun ? 'Dry run complete - no Stripe objects created.' : 'plan_prices sync complete.');

        return self::SUCCESS;
    }

    private function ensurePrice(Plan $plan, string $countryCode, string $currency, string $interval, int $amount, bool $dryRun, array &$productCache): void
    {
        $row = PlanPrice::on('mysql')->firstOrNew([
            'plan_id'      => $plan->id,
            'country_code' => $countryCode,
            'interval'     => $interval,
        ]);

        $row->currency = $currency;
        $row->amount   = $amount;

        if ($row->stripe_price_id) {
            $this->line("skip {$plan->slug} {$countryCode} {$interval}: already has {$row->stripe_price_id}");
            if (!$dryRun) {
                $row->save();
            }
            return;
        }

        $this->line("creating {$plan->slug} {$countryCode} {$interval} {$currency} " . number_format($amount / 100, 2));

        if ($dryRun) {
            return;
        }

        $productId = $productCache[$plan->id] ??= $this->ensureProduct($plan);

        $stripePrice = StripePrice::create([
            'product'    => $productId,
            'currency'   => strtolower($currency),
            'unit_amount'=> $amount,
            'recurring'  => ['interval' => $interval === 'yearly' ? 'year' : 'month'],
            'metadata'   => ['plan_id' => $plan->id, 'plan_slug' => $plan->slug, 'country_code' => $countryCode],
        ]);

        $row->stripe_price_id = $stripePrice->id;
        $row->save();
    }

    private function ensureProduct(Plan $plan): string
    {
        $name = json_decode($plan->getRawOriginal('name'), true)['en'] ?? $plan->slug;

        $product = StripeProduct::create([
            'name'     => 'Fakturalista - ' . $name,
            'metadata' => ['plan_id' => $plan->id, 'plan_slug' => $plan->slug],
        ]);

        return $product->id;
    }
}
