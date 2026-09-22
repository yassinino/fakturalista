<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The subscription page must not sell capabilities Fakturalista doesn't
 * actually have (launch-critical billing audit): "Factures récurrentes",
 * "Relances de paiement" and "Accès API" have no implementation anywhere
 * in the codebase (no recurring-invoice model/job, no reminder job/mailer,
 * no API token/auth system) - removed rather than left as false claims.
 * "Export Excel et PDF" is reworded to drop the Excel half (PDF export is
 * real; no Excel export library or endpoint exists) - matches the wording
 * already used on the Starter plan's own PDF export item.
 */
return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        DB::connection('mysql')->table('plan_marketing_items')
            ->where('text_en', 'Recurring invoices')
            ->orWhere('text_en', 'Payment reminders')
            ->orWhere('text_en', 'API access')
            ->delete();

        DB::connection('mysql')->table('plan_marketing_items')
            ->where('text_en', 'Excel & PDF export')
            ->update([
                'text_fr' => 'Export PDF professionnel',
                'text_en' => 'Professional PDF export',
                'text_es' => 'Exportación PDF',
            ]);
    }

    public function down(): void
    {
        // Content-only fix, not restored - the removed claims described
        // features that never existed in code; there is nothing correct
        // to roll back to.
    }
};
