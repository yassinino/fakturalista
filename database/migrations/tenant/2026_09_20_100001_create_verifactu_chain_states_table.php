<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per emitting NIF within the tenant (almost always exactly one,
 * but the XSD explicitly allows one installation to serve multiple
 * "obligados tributarios" - IndicadorMultiplesOT - so this is keyed by
 * nif_emisor rather than assumed 1:1 with the tenant).
 *
 * This is the row VerifactuChainService locks (SELECT ... FOR UPDATE)
 * before generating a new record, so two concurrent issuances for the
 * same NIF can never compute a record against the same "previous hash"
 * (same primitive InvoiceNumberingService already uses for numbering).
 *
 * Cross-tenant chain isolation is structural, not enforced by a column
 * here: this table lives in the tenant database, exactly like `invoices`
 * does - there is no tenant_id to forget a WHERE clause on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verifactu_chain_states', function (Blueprint $table) {
            $table->id();
            $table->string('nif_emisor', 20)->unique();
            $table->string('last_huella', 64)->nullable();
            $table->unsignedBigInteger('last_verifactu_record_id')->nullable();
            $table->timestamps();

            $table->foreign('last_verifactu_record_id', 'verifactu_chain_states_last_record_foreign')
                ->references('id')->on('verifactu_records')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verifactu_chain_states');
    }
};
