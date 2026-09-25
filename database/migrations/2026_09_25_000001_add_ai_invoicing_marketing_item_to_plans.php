<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds one qualitative marketing line ("AI-assisted invoicing") to every
 * active plan's benefit list, via the existing plan_marketing_items
 * mechanism (PlanPricingPresenter::benefits()) - the AI invoice feature
 * isn't plan-gated in the app itself, so every plan gets the same line.
 * Content only: no plan/price/limit/feature-toggle data is touched.
 */
return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        $texts = [
            'text_fr' => 'Facturation assistée par IA',
            'text_en' => 'AI-assisted invoicing',
            'text_es' => 'Facturación asistida por IA',
        ];

        $planIds = DB::connection('mysql')->table('plans')
            ->whereIn('slug', ['starter', 'pro', 'business'])
            ->pluck('id');

        foreach ($planIds as $planId) {
            $exists = DB::connection('mysql')->table('plan_marketing_items')
                ->where('plan_id', $planId)
                ->where('text_fr', $texts['text_fr'])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::connection('mysql')->table('plan_marketing_items')->insert([
                'plan_id'        => $planId,
                'text_fr'        => $texts['text_fr'],
                'text_en'        => $texts['text_en'],
                'text_es'        => $texts['text_es'],
                'icon'           => '✨',
                'sort_order'     => 10,
                'is_highlighted' => false,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::connection('mysql')->table('plan_marketing_items')
            ->where('text_fr', 'Facturation assistée par IA')
            ->delete();
    }
};
