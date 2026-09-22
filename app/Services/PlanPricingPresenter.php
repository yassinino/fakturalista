<?php

namespace App\Services;

use App\Models\Plan;
use Illuminate\Support\Collection;

/**
 * Turns raw Plan/PlanLimit/PlanPrice/Feature data into the small,
 * customer-friendly set of lines the public pricing page actually shows.
 *
 * The database stays the single source of truth for every number and
 * every feature toggle - this class only decides HOW to phrase and
 * PRIORITIZE what Filament already contains, never invents a value. A
 * Filament change (limit, feature, price) is picked up on the very next
 * render, no code change involved.
 *
 * Rules (see the pricing redesign task):
 *  - Never advertise a limit that is exactly 0 (a missing capability,
 *    e.g. Starter having no quotes, must simply not appear - not "0 devis").
 *  - Combine limits into one elegant line when they are ALL genuinely
 *    unlimited together - never claim "unlimited" for a capped value.
 *  - Users/seats are never shown - there is no real invite/team
 *    management workflow yet, so advertising a seat count would be
 *    misleading regardless of what plan_limits says.
 *  - Features that exist on every active plan (e.g. PDF export, email
 *    sending) are baseline, not a differentiator - shown last, only if
 *    there is still room in the 5-benefit budget.
 */
class PlanPricingPresenter
{
    private const MAX_BENEFITS = 5;

    /**
     * @param Collection<int, Plan> $allPlans Every active plan being shown together - needed to know which features are "baseline" (on all of them) vs differentiating.
     */
    public static function baselineFeatureSlugs(Collection $allPlans): Collection
    {
        $slugSets = $allPlans->map(fn (Plan $p) => $p->features->pluck('slug'));

        if ($slugSets->isEmpty()) {
            return collect();
        }

        return $slugSets->reduce(
            fn (?Collection $carry, Collection $slugs) => $carry === null ? $slugs : $carry->intersect($slugs)
        ) ?? collect();
    }

    public static function present(Plan $plan, string $locale, string $market, Collection $baselineFeatureSlugs): array
    {
        $planPrice = $plan->priceFor($market, 'monthly');

        $invoices  = $plan->getLimit('invoices_per_month');
        $customers = $plan->getLimit('customers');
        $quotes    = $plan->getLimit('quotes');
        $products  = $plan->getLimit('products');

        return [
            'slug'          => $plan->slug,
            'name'          => $plan->translate('name', $locale),
            'badge'         => $plan->translate('badge', $locale),
            'description'   => $plan->translate('short_description', $locale),
            'button_text'   => $plan->translate('button_text', $locale) ?: __('site.pricing.default_cta'),
            'button_url'    => $plan->button_url ? url(parse_url($plan->button_url, PHP_URL_PATH) ?: '/register') : url('/register'),
            'is_featured'   => (bool) $plan->is_featured,
            'price'         => $planPrice ? self::formatPrice($planPrice->amount) : null,
            'currency'      => $planPrice ? ($planPrice->currency === 'EUR' ? '€' : $planPrice->currency) : null,
            'capacity_line' => self::capacityLine($invoices),
            'benefits'      => self::benefits($plan, $locale, $customers, $quotes, $products, $baselineFeatureSlugs),
        ];
    }

    /**
     * Whole amounts (Morocco's 119/209/799 MAD) render without decimals;
     * fractional ones (Spain's 4.90/9.90/19.90 EUR) keep them - based on
     * the actual stored amount, never a hardcoded per-currency rule.
     */
    private static function formatPrice(int $amountInCents): string
    {
        $amount = $amountInCents / 100;

        return $amount == floor($amount)
            ? number_format($amount, 0, ',', '')
            : number_format($amount, 2, ',', '');
    }

    private static function capacityLine(?int $invoices): string
    {
        return $invoices === null
            ? __('site.pricing.capacity_invoices_unlimited')
            : __('site.pricing.capacity_invoices', ['count' => $invoices]);
    }

    private static function benefits(Plan $plan, string $locale, ?int $customers, ?int $quotes, ?int $products, Collection $baselineFeatureSlugs): array
    {
        $lines = [];

        // Customers + quotes + products: combine into one line only when
        // ALL three are genuinely unlimited together (Business today);
        // otherwise list what applies individually, and silently skip
        // anything that is exactly 0 - never present a missing
        // capability as a "benefit".
        if ($customers === null && $quotes === null && $products === null) {
            $lines[] = __('site.pricing.benefit_unlimited_management');
        } else {
            if ($customers === null) {
                $lines[] = __('site.pricing.benefit_customers_unlimited');
            } elseif ($customers > 0) {
                $lines[] = __('site.pricing.benefit_customers', ['count' => $customers]);
            }

            if ($quotes === null && $products === null) {
                $lines[] = __('site.pricing.benefit_quotes_products_unlimited');
            } else {
                if ($quotes === null) {
                    $lines[] = __('site.pricing.benefit_quotes_unlimited');
                } elseif ($quotes > 0) {
                    $lines[] = __('site.pricing.benefit_quotes', ['count' => $quotes]);
                }

                if ($products === null) {
                    $lines[] = __('site.pricing.benefit_products_unlimited');
                } elseif ($products > 0) {
                    $lines[] = __('site.pricing.benefit_products', ['count' => $products]);
                }
            }
        }

        // Features - the ones this plan has that NOT every plan has
        // (the actual differentiators) take priority over baseline ones.
        $differentiating = $plan->features->reject(fn ($f) => $baselineFeatureSlugs->contains($f->slug));
        $baseline        = $plan->features->filter(fn ($f) => $baselineFeatureSlugs->contains($f->slug));

        foreach ($differentiating as $feature) {
            $lines[] = $feature->{"name_{$locale}"} ?? $feature->name_fr;
        }

        // The one qualitative (support-tier) marketing line, if any -
        // still database-driven (Filament's "Arguments marketing"), just
        // never a raw number restatement.
        foreach ($plan->marketingItems as $item) {
            $lines[] = $item->{"text_{$locale}"} ?? $item->text_fr;
        }

        foreach ($baseline as $feature) {
            $lines[] = $feature->{"name_{$locale}"} ?? $feature->name_fr;
        }

        return array_slice($lines, 0, self::MAX_BENEFITS);
    }
}
