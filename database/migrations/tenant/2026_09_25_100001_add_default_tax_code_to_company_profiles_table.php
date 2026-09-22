<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Morocco Phase 1C.2 (docs/morocco-phase-1c2-tax-configuration.md §11).
 * A tenant's convenience default when adding a new line - stores a
 * TaxPreset code (e.g. "MA_TVA_20", "ES_IVA_21"), never a country-specific
 * column. Purely a UI default: it never affects existing invoices/quotes
 * and is never read by DocumentCalculationService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->string('default_tax_code', 40)->nullable()->after('if_number');
        });
    }

    public function down(): void
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->dropColumn('default_tax_code');
        });
    }
};
