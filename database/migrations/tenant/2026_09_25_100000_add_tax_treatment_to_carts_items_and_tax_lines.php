<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Morocco Phase 1C.2 (docs/morocco-phase-1c2-tax-configuration.md §1/§9).
 * A generic (never Morocco-specific) semantic label distinguishing a
 * legally exempt operation from an ordinary taxable line that merely
 * happens to be 0% - see App\Services\Tax\TaxTreatment. Purely additive,
 * default 'taxable' so every existing row (Spanish or otherwise) keeps
 * its current meaning unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->string('tax_treatment', 20)->default('taxable')->after('vta');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->string('tax_treatment', 20)->default('taxable')->after('vta');
        });

        Schema::table('invoice_tax_lines', function (Blueprint $table) {
            $table->string('treatment', 20)->default('taxable')->after('rate');
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropColumn('tax_treatment');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('tax_treatment');
        });

        Schema::table('invoice_tax_lines', function (Blueprint $table) {
            $table->dropColumn('treatment');
        });
    }
};
