<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Plan;

class PlanController extends Controller
{
    /**
     * Retourne la liste des plans actifs (base centrale)
     * GET /api/plans
     */
    public function index(Request $request)
    {
        $plans = Plan::where('active', true)
            ->orderBy('amount', 'asc')
            ->get([
                'id',
                'name',
                'amount',
                'currency',
                'interval',
                'paypal_plan_id',
                'stripe_price_id',
                'features',
            ])
            ->map(function ($plan) {
                return [
                    'id'          => $plan->id,
                    'name'        => $plan->name,
                    'features'    => json_decode($plan->features),
                    'price'       => number_format($plan->amount / 100, 2, '.', ''),
                    'currency'    => strtoupper($plan->currency ?? 'EUR'),
                    'interval'    => $plan->interval ?? 'month',
                    'highlight'   => $plan->name === 'Empresa' ? true : false,
                ];
            });

        return response()->json([
            'success' => true,
            'plans'   => $plans,
        ]);
    }

    /**
     * Retourne les infos d’un plan spécifique
     * GET /api/plans/{id}
     */
    public function show($id)
    {
        $plan = Plan::where('active', true)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'plan' => [
                'id'          => $plan->id,
                'name'        => $plan->name,
                'price'       => number_format($plan->amount / 100, 2, '.', ''),
                'currency'    => strtoupper($plan->currency ?? 'EUR'),
                'interval'    => $plan->interval ?? 'month',
                'stripe_price_id' => $plan->stripe_price_id,
                'paypal_plan_id' => $plan->paypal_plan_id,
            ],
        ]);
    }
}