<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RD 1619/2012 requires rectificative invoices to be issued in a series
 * specific to them, separate from ordinary invoices. This is the display
 * prefix for that series (the counter itself lives in
 * invoice_number_sequences under its own series_key).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->string('rectification_prefix', 20)->default('R')->after('invoice_number_format');
        });
    }

    public function down(): void
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->dropColumn('rectification_prefix');
        });
    }
};
