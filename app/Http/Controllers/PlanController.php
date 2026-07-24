<?php

namespace App\Http\Controllers;

use App\Models\Plan;
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
        $locale = app()->getLocale();

        $plans = Plan::on('mysql')
            ->where('active', true)
            ->with(['limits', 'features', 'marketingItems'])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Plan $plan) => $this->formatPlan($plan, $locale));

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
        $locale = app()->getLocale();

        $plan = Plan::on('mysql')
            ->where('active', true)
            ->with(['limits', 'features', 'marketingItems'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'plan'    => $this->formatPlan($plan, $locale),
        ]);
    }

    private function formatPlan(Plan $plan, string $locale): array
    {
        $name = json_decode($plan->getRawOriginal('name'), true) ?? [];

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
            'price'                 => number_format($plan->monthly_price / 100, 2, '.', ''),
            'monthly_price'         => $plan->monthly_price,
            'yearly_price'          => $plan->yearly_price,
            'currency'              => strtoupper($plan->currency ?? 'EUR'),
            'trial_days'            => $plan->trial_days,
            'stripe_price_id'       => $plan->stripe_price_id_monthly ?? $plan->stripe_price_id,
            'stripe_price_id_yearly'=> $plan->stripe_price_id_yearly,
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
