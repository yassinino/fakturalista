<?php

namespace App\Console\Commands;

use App\Models\Plan;
use App\Models\PlanPrice;
use Illuminate\Console\Command;
use Stripe\Exception\InvalidRequestException;
use Stripe\Price as StripePrice;
use Stripe\Product as StripeProduct;
use Stripe\Stripe;

/**
 * Seeds/repairs `plan_prices` (Starter/Pro/Business x Morocco-MAD /
 * Spain-EUR) and creates the matching Stripe Product/Price objects in
 * whichever Stripe account config('services.stripe.secret') currently
 * points to.
 *
 * A non-null stripe_price_id is NEVER trusted on its own - a Price id is
 * only meaningful within the Stripe account it was created in, and this
 * account can change (e.g. STRIPE_SECRET rotated to a different Stripe
 * account) while the DB still holds the OLD account's ids. Every row with
 * a stored id is actively re-verified against the CURRENT account
 * (retrieve + currency/amount/interval/plan match) before being reused;
 * a row that fails - not found, or found but wrong - is replaced with a
 * freshly created Price in the current account. Product reuse is the
 * same: never assume a cached/previous product id is still valid, always
 * search the CURRENT account's products for one that actually belongs to
 * this plan before creating a new one.
 *
 * Idempotent: re-running after every id is already valid in the current
 * account verifies all 9 rows and creates nothing.
 *
 * Prices below are the real, explicitly-decided launch prices (Morocco
 * MAD figures given directly by the business owner; Spain EUR figures
 * already live in `plans.monthly_price`/`yearly_price`) - never invented
 * or currency-converted.
 */
class SyncPlanPrices extends Command
{
    protected $signature = 'plans:sync-prices {--dry-run : Only print what would change, create nothing in Stripe}';

    protected $description = 'Verify/repair plan_prices against the CURRENT Stripe account and (re)create Prices that are missing, stale, or belong to a different account';

    /**
     * @var array<string, array{monthly:int, yearly:?int}> amounts in minor units (cents)
     */
    private const MOROCCO_MAD = [
        'starter'  => ['monthly' => 11900, 'yearly' => null],
        'pro'      => ['monthly' => 20900, 'yearly' => null],
        'business' => ['monthly' => 79900, 'yearly' => null],
    ];

    /** @var array<int, string> plan_id => Stripe product id, cached for this run only */
    private array $productCache = [];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        Stripe::setApiKey(config('services.stripe.secret'));

        $plans = Plan::on('mysql')->where('active', true)->get()->keyBy('slug');
        $this->productCache = [];

        foreach ($plans as $slug => $plan) {
            // ── Spain (EUR) - reuses the amounts already on the plan row,
            // never converted/changed here ──
            $this->ensurePrice($plan, 'ES', 'EUR', 'monthly', $plan->monthly_price, $dryRun);
            if ($plan->yearly_price) {
                $this->ensurePrice($plan, 'ES', 'EUR', 'yearly', $plan->yearly_price, $dryRun);
            }

            // ── Morocco (MAD) - explicit launch prices, monthly only for now ──
            $ma = self::MOROCCO_MAD[$slug] ?? null;
            if ($ma) {
                $this->ensurePrice($plan, 'MA', 'MAD', 'monthly', $ma['monthly'], $dryRun);
                if ($ma['yearly']) {
                    $this->ensurePrice($plan, 'MA', 'MAD', 'yearly', $ma['yearly'], $dryRun);
                }
            }
        }

        $this->info($dryRun ? 'Dry run complete - no Stripe objects created.' : 'plan_prices sync complete.');

        return self::SUCCESS;
    }

    private function ensurePrice(Plan $plan, string $countryCode, string $currency, string $interval, int $amount, bool $dryRun): void
    {
        $row = PlanPrice::on('mysql')->firstOrNew([
            'plan_id'      => $plan->id,
            'country_code' => $countryCode,
            'interval'     => $interval,
        ]);

        $row->currency = $currency;
        $row->amount   = $amount;

        if ($row->stripe_price_id) {
            $verified = $this->verifyExistingPrice($row->stripe_price_id, $plan, $currency, $amount, $interval);

            if ($verified) {
                $this->line("verified {$plan->slug} {$countryCode} {$interval}: {$row->stripe_price_id} is valid in the current Stripe account");
                if (!$dryRun) {
                    $row->save();
                }
                return;
            }

            $this->warn("stale {$plan->slug} {$countryCode} {$interval}: {$row->stripe_price_id} does not resolve to a matching Price in the CURRENT Stripe account - replacing");
            $row->stripe_price_id = null;
        }

        $this->line("creating {$plan->slug} {$countryCode} {$interval} {$currency} " . number_format($amount / 100, 2));

        if ($dryRun) {
            return;
        }

        $productId = $this->productCache[$plan->id] ??= $this->ensureProduct($plan);

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

    /**
     * Retrieves the given Price id from the CURRENT Stripe account and
     * checks it actually matches what this row is supposed to be -
     * currency, amount, recurring interval, and the plan it was created
     * for (via the metadata this same command always writes on create).
     * Returns false for "not found in this account" and for "found, but
     * wrong" alike - both mean the id cannot be trusted.
     */
    private function verifyExistingPrice(string $stripePriceId, Plan $plan, string $currency, int $amount, string $interval): bool
    {
        try {
            $price = StripePrice::retrieve($stripePriceId);
        } catch (InvalidRequestException $e) {
            // "No such price" - most commonly because the id belongs to a
            // different Stripe account than the one currently configured.
            return false;
        }

        if (!$price->active) {
            return false;
        }
        if (strtolower($price->currency) !== strtolower($currency)) {
            return false;
        }
        if ((int) $price->unit_amount !== $amount) {
            return false;
        }
        $expectedInterval = $interval === 'yearly' ? 'year' : 'month';
        if (($price->recurring->interval ?? null) !== $expectedInterval) {
            return false;
        }
        // Plan ownership: the metadata this command itself always sets at
        // creation time - a loose (==) comparison since Stripe metadata
        // values are always strings.
        if (($price->metadata->plan_id ?? null) != $plan->id) {
            return false;
        }

        return true;
    }

    /**
     * Finds an existing Stripe Product that genuinely belongs to this plan
     * IN THE CURRENT ACCOUNT (never assumed from a cached/previous id),
     * or creates a new one. Prevents duplicate "Fakturalista - X" products
     * from piling up across repeated runs/account changes.
     */
    private function ensureProduct(Plan $plan): string
    {
        foreach (StripeProduct::all(['limit' => 100])->autoPagingIterator() as $product) {
            if ($product->active && ($product->metadata['plan_id'] ?? null) == $plan->id) {
                return $product->id;
            }
        }

        $name = json_decode($plan->getRawOriginal('name'), true)['en'] ?? $plan->slug;

        $product = StripeProduct::create([
            'name'     => 'Fakturalista - ' . $name,
            'metadata' => ['plan_id' => $plan->id, 'plan_slug' => $plan->slug],
        ]);

        return $product->id;
    }
}
