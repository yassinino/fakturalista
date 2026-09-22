<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Services\PlanPricingPresenter;
use App\Services\TenantContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PlanController extends Controller
{
    /**
     * Active plans ordered by sort_order, with limits and features.
     * GET /api/plans
     *
     * One query set for the whole list (no per-plan request) - see
     * formatPlan()'s own note on why $baselineFeatureSlugs is computed
     * once here rather than per plan.
     */
    public function index(Request $request): JsonResponse
    {
        $locale  = app()->getLocale();
        $country = app(TenantContextService::class)->country();

        $plans = Plan::on('mysql')
            ->where('active', true)
            ->with(['limits', 'features', 'marketingItems', 'prices'])
            ->orderBy('sort_order')
            ->get();

        $baselineFeatureSlugs = PlanPricingPresenter::baselineFeatureSlugs($plans);

        return response()->json([
            'success' => true,
            'plans'   => $plans->map(fn (Plan $plan) => $this->formatPlan($plan, $locale, $country, $baselineFeatureSlugs)),
        ]);
    }

    /**
     * Single plan details.
     * GET /api/plans/{id}
     */
    public function show(int $id): JsonResponse
    {
        $locale  = app()->getLocale();
        $country = app(TenantContextService::class)->country();

        // Baseline features ("on every plan") only mean something relative
        // to every OTHER active plan too, so this still loads the full
        // active set (a handful of rows) even though only one is returned.
        $plans = Plan::on('mysql')
            ->where('active', true)
            ->with(['limits', 'features', 'marketingItems', 'prices'])
            ->get();

        $plan = $plans->firstWhere('id', $id);
        abort_if(!$plan, 404);

        $baselineFeatureSlugs = PlanPricingPresenter::baselineFeatureSlugs($plans);

        return response()->json([
            'success' => true,
            'plan'    => $this->formatPlan($plan, $locale, $country, $baselineFeatureSlugs),
        ]);
    }

    /**
     * Price/currency come from `plan_prices` (per-market: Morocco MAD,
     * Spain EUR - see the SyncPlanPrices command and the "no duplicated
     * plan rows per country" architecture note on the plan_prices
     * migration), resolved for the REQUESTING tenant's own country -
     * never a static column on the plan row itself. A tenant must never
     * see a currency Stripe isn't actually configured to charge for the
     * matching Checkout Session (see SubscriptionController).
     *
     * `benefits`/`capacity_line` are built by PlanPricingPresenter - the
     * SAME class the public /pricing page uses (HomeController::pricing()),
     * so the authenticated subscription page and the public pricing page
     * can never disagree about what a plan actually includes. Everything
     * else here is kept field-for-field identical to before so the
     * existing subscription.vue keeps working unmodified except for
     * swapping its old marketing_items-only list for this richer one.
     */
    private function formatPlan(Plan $plan, string $locale, string $country, Collection $baselineFeatureSlugs): array
    {
        $name = json_decode($plan->getRawOriginal('name'), true) ?? [];

        $monthly = $plan->priceFor($country, 'monthly');
        $yearly  = $plan->priceFor($country, 'yearly');

        return [
            'id'                    => $plan->id,
            'slug'                  => $plan->slug,
            'name'                  => $name[$locale] ?? $name['fr'] ?? $name['en'] ?? $plan->slug,
            'badge'                 => $plan->translate('badge', $locale),
            'short_description'     => $plan->translate('short_description', $locale),
            'icon'                  => $plan->icon,
            'color'                 => $plan->color,
            'is_featured'           => $plan->is_featured,
            'sort_order'            => $plan->sort_order,
            'price'                 => $monthly ? number_format($monthly->amount / 100, 2, '.', '') : null,
            'monthly_price'         => $monthly?->amount,
            'yearly_price'          => $yearly?->amount,
            // Only true once a real Stripe Price exists for this market's
            // yearly interval (Morocco: monthly only for now) - drives the
            // frontend's yearly toggle instead of it inventing a discount.
            'yearly_available'      => $yearly !== null,
            'currency'              => $monthly?->currency ?? 'EUR',
            'trial_days'            => $plan->trial_days,
            'stripe_price_id'       => $monthly?->stripe_price_id,
            'stripe_price_id_yearly'=> $yearly?->stripe_price_id,
            'paypal_plan_id'        => $plan->paypal_plan_id,
            'button_text'           => $plan->translate('button_text', $locale),
            'button_url'            => $plan->button_url,
            'button_action'         => $plan->button_action,
            // One capacity headline + up to 6 prioritized benefit lines -
            // the exact same selection/phrasing logic /pricing uses.
            'capacity_line'         => PlanPricingPresenter::capacityLine($plan->getLimit('invoices_per_month')),
            'benefits'              => PlanPricingPresenter::benefits($plan, $locale, $baselineFeatureSlugs),
            // Raw numbers kept too (never removed) for any consumer that
            // needs the exact figures rather than the phrased benefit list.
            'limits'                => collect($plan->limits ?? [])->mapWithKeys(fn ($l) => [
                $l->resource => $l->value,
            ]),
            'features'              => collect($plan->features ?? [])->map(fn ($f) => [
                'slug' => $f->slug,
                'name' => $f->{"name_{$locale}"} ?? $f->name_fr,
            ])->values(),
        ];
    }
}
