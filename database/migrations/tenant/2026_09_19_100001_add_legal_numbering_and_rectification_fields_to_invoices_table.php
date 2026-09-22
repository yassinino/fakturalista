<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive only. No existing column is altered or backfilled - historical
 * invoices keep their existing `reference` value untouched and simply have
 * NULL in every new column below. Retroactively assigning them a
 * `invoice_series`/`invoice_number` would misrepresent the legal record of
 * documents issued before this numbering scheme existed, so this migration
 * deliberately does not attempt it (see the Phase 2A report for the
 * reasoning).
 *
 * `invoice_series`/`invoice_number` are only ever written by
 * InvoiceNumberingService, at the moment an invoice is actually issued
 * (or a rectification is created) - never at draft-creation time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('invoice_series')->nullable()->after('reference');
            $table->unsignedBigInteger('invoice_number')->nullable()->after('invoice_series');

            // AEAT ClaveTipoFacturaType: F1 (complete), F2 (simplified), F3
            // (substitution of a simplified invoice), R1-R5 (rectificative).
            // Null until issued; store.php/§18-Q1 decision means we do
            // support R1-R5 fully in this phase, not just model the column.
            $table->string('invoice_type', 2)->nullable()->after('invoice_number');

            // 'S' (por sustitución) or 'I' (por diferencias) - RD 1619/2012
            // art. 15.5. Only set on rectificative invoices.
            $table->char('rectification_type', 1)->nullable()->after('invoice_type');

            // Self-reference: the original invoice this one rectifies.
            $table->unsignedBigInteger('rectifies_invoice_id')->nullable()->after('rectification_type');

            // Business-level reason captured from the guided UI - kept
            // distinct from invoice_type, which is the technical AEAT code
            // derived FROM this reason (see InvoiceRectificationService).
            $table->string('rectification_reason')->nullable()->after('rectifies_invoice_id');

            // Populated when an invoice is annulled via the VERI*FACTU
            // "registro de anulación" path (no real underlying operation),
            // as opposed to being rectified.
            $table->string('cancellation_reason')->nullable()->after('rectification_reason');

            // AEAT DescripcionOperacion - distinct from the free-text `note`
            // field, which stays as-is for internal/customer-facing notes.
            $table->string('descripcion_operacion', 500)->nullable()->after('cancellation_reason');
        });

        // Separate statement: adding a unique index and a self-referencing
        // FK in the same Blueprint as the new columns is fine in MySQL, but
        // kept in its own call for clarity and easier rollback.
        Schema::table('invoices', function (Blueprint $table) {
            // MySQL unique indexes treat every NULL as distinct, so any
            // number of drafts with NULL/NULL are allowed - only real,
            // non-null (series, number) pairs are protected against
            // collision. This is the DB-level duplicate-numbering guard.
            $table->unique(['invoice_series', 'invoice_number'], 'invoices_series_number_unique');

            $table->foreign('rectifies_invoice_id', 'invoices_rectifies_invoice_id_foreign')
                ->references('id')->on('invoices')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign('invoices_rectifies_invoice_id_foreign');
            $table->dropUnique('invoices_series_number_unique');
            $table->dropColumn([
                'invoice_series',
                'invoice_number',
                'invoice_type',
                'rectification_type',
                'rectifies_invoice_id',
                'rectification_reason',
                'cancellation_reason',
                'descripcion_operacion',
            ]);
        });
    }
};
