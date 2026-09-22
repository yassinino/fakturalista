<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Two data fixes needed for the public pricing page to become fully
 * dynamic (plan_limits / plan_features / plan_prices as the single
 * source of truth, per the pricing-page rework):
 *
 * 1. plan_marketing_items still had free-text bullets that only restated
 *    a plan_limits number ("25 clients", "250 factures / mois",
 *    "Clients illimités"...) or a plan_features row ("Export PDF
 *    professionnel", "Paiements en ligne (Stripe)", "Tableau de bord
 *    complet"...). Once the pricing page renders limits/features
 *    directly from those tables, these free-text duplicates would show
 *    twice and - worse - would silently go stale again if only the
 *    structured table is edited in Filament (exactly today's bug).
 *    Removed; each plan keeps only its one genuinely-qualitative,
 *    non-structured claim (its support-tier line).
 *
 * 2. plan_features still had recurring_invoices/payment_reminders/
 *    api_access/excel_export attached to Pro/Business. An earlier
 *    audit already established none of these have any implementation
 *    anywhere in the codebase and removed them from the marketing text -
 *    but the underlying pivot rows were left in place (a separate,
 *    then-undiscovered bug: $plan->features always returned null due to
 *    the legacy plans.features column shadowing the relationship, so
 *    nothing ever rendered them). Now that that bug is fixed and the
 *    pricing page renders $plan->features directly, these unimplemented
 *    claims would resurface - detached here for the same reason they
 *    were removed from the marketing text.
 */
return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        DB::connection('mysql')->table('plan_marketing_items')
            ->whereIn('text_en', [
                '25 invoices / month', '25 clients', '1 user',
                '250 invoices / month', '250 clients', '5 users',
                'Unlimited products & quotes', 'Quote creation',
                'Professional PDF export',
                'Unlimited invoices', 'Unlimited clients', 'Unlimited users',
                'Online payments (Stripe)', 'Full dashboard',
            ])
            ->delete();

        $unimplementedFeatureIds = DB::connection('mysql')->table('features')
            ->whereIn('slug', ['recurring_invoices', 'payment_reminders', 'api_access', 'excel_export'])
            ->pluck('id');

        DB::connection('mysql')->table('plan_features')
            ->whereIn('feature_id', $unimplementedFeatureIds)
            ->delete();
    }

    public function down(): void
    {
        // Content-only cleanup of duplicated/unimplemented data - nothing
        // correct to restore.
    }
};
