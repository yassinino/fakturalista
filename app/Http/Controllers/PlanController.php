<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Services\TenantContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    /**
     * Active plans ordered by sort_order, with limits and features.
     * GET /api/plans
     */
    public function index(Request $request): JsonResponse
    {
        $locale  = app()->getLocale();
        $country = app(TenantContextService::class)->country();

        $plans = Plan::on('mysql')
            ->where('active', true)
            ->with(['limits', 'features', 'marketingItems', 'prices'])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Plan $plan) => $this->formatPlan($plan, $locale, $country));

        return response()->json([
            'success' => true,
            'plans'   => $plans,
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

        $plan = Plan::on('mysql')
            ->where('active', true)
            ->with(['limits', 'features', 'marketingItems', 'prices'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'plan'    => $this->formatPlan($plan, $locale, $country),
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
     */
    private function formatPlan(Plan $plan, string $locale, string $country): array
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
            'features'              => collect($plan->features ?? [])->map(fn ($f) => [
                'slug' => $f->slug,
                'name' => $f->{"name_{$locale}"} ?? $f->name_fr,
            ])->values(),
            'limits'                => collect($plan->limits ?? [])->mapWithKeys(fn ($l) => [
                $l->resource => $l->value,
            ]),
            'marketing_items'       => collect($plan->marketingItems ?? [])->map(fn ($m) => [
                'text'           => $m->{"text_{$locale}"} ?? $m->text_fr,
                'icon'           => $m->icon,
                'is_highlighted' => $m->is_highlighted,
            ])->values(),
        ];
    }
}
