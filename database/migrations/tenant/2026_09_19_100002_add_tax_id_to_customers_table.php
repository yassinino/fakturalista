<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spanish NIF/CIF/NIE for the customer - distinct from the existing generic
 * `vat_number` (EU VAT number format) and the Moroccan `ice` field. Kept
 * nullable: a customer NIF is only mandatory for a complete invoice (F1),
 * not for a simplified one (F2) - see InvoiceController::issue() and
 * RD 1619/2012 art. 6/7.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('tax_id')->nullable()->after('vat_number');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('tax_id');
        });
    }
};
