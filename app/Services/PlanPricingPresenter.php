<?php

namespace App\Services;

use App\Models\Plan;
use Illuminate\Support\Collection;

/**
 * THE single authoritative transformation from Plan/PlanLimit/PlanPrice/
 * Feature (Filament-managed) into the small, customer-friendly set of
 * facts both the public pricing page (HomeController::pricing() /
 * pricing.blade.php) and the authenticated subscription page
 * (PlanController -> subscription.vue) show.
 *
 * Both pages call the SAME benefits()/capacityLine()/formatPrice() here -
 * there is exactly one place that decides "what does this plan actually
 * offer, worth telling a customer about". They render differently (public
 * marketing card vs. authenticated plan-switcher card), but the facts -
 * price, capacity, benefit list - can never disagree, because both read
 * them from here rather than reimplementing the selection logic.
 *
 * The database stays the single source of truth for every number and
 * every feature toggle - this class only decides HOW to phrase and
 * PRIORITIZE what Filament already contains, never invents a value. A
 * Filament change (limit, feature, price) is picked up on the very next
 * render, no code change involved.
 *
 * Rules:
 *  - Never advertise a limit that is exactly 0 (a missing capability,
 *    e.g. Starter having no quotes, must simply not appear - not "0 devis").
 *  - Combine limits into one elegant line when they are ALL genuinely
 *    unlimited together - never claim "unlimited" for a capped value.
 *  - Users/seats are NEVER shown - there is no real invite/team
 *    management workflow yet, so advertising a seat count would be
 *    misleading regardless of what plan_limits says.
 *  - Features that exist on every active plan (e.g. PDF export, email
 *    sending) are baseline, not a differentiator - shown last, only if
 *    there is still room in the benefit budget.
 *  - Disabled/unattached features never appear (plan_features is the
 *    only source consulted).
 */
class PlanPricingPresenter
{
    private const MAX_BENEFITS = 6;

    /**
     * @param Collection<int, Plan> $allPlans Every active plan being compared - needed to know which features are "baseline" (on all of them) vs differentiating.
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

    /**
     * Full public-marketing card: everything present() below plus the
     * fields only the acquisition-focused /pricing page needs (CTA
     * button target, formatted currency symbol). Kept separate from
     * PlanController's own response shape, which the authenticated Vue
     * page already depends on field-for-field.
     */
    public static function present(Plan $plan, string $locale, string $market, Collection $baselineFeatureSlugs): array
    {
        $planPrice = $plan->priceFor($market, 'monthly');

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
            'capacity_line' => self::capacityLine($plan->getLimit('invoices_per_month')),
            'benefits'      => self::benefits($plan, $locale, $baselineFeatureSlugs),
        ];
    }

    /**
     * Whole amounts (Morocco's 119/209/799 MAD) render without decimals;
     * fractional ones (Spain's 4.90/9.90/19.90 EUR) keep them - based on
     * the actual stored amount, never a hardcoded per-currency rule.
     */
    public static function formatPrice(int $amountInCents): string
    {
        $amount = $amountInCents / 100;

        return $amount == floor($amount)
            ? number_format($amount, 0, ',', '')
            : number_format($amount, 2, ',', '');
    }

    public static function capacityLine(?int $invoices): string
    {
        return $invoices === null
            ? __('site.pricing.capacity_invoices_unlimited')
            : __('site.pricing.capacity_invoices', ['count' => $invoices]);
    }

    /**
     * Up to MAX_BENEFITS customer-facing lines, in priority order:
     * secondary capacity (customers/quotes/products, combined elegantly
     * when genuinely unlimited together) → differentiating features →
     * qualitative marketing line → baseline features. Requires
     * 'limits', 'features' and 'marketingItems' eager-loaded on $plan.
     */
    public static function benefits(Plan $plan, string $locale, Collection $baselineFeatureSlugs): array
    {
        $customers = $plan->getLimit('customers');
        $quotes    = $plan->getLimit('quotes');
        $products  = $plan->getLimit('products');

        $lines = [];

        // Customers + quotes + products: combine into one line only when
        // ALL three are genuinely unlimited together (Business today);
        // otherwise list what applies individually, and silently skip
        // anything that is exactly 0 - never present a missing
        // capability as a "benefit". Users/seats are deliberately never
        // considered here - see class docblock.
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
