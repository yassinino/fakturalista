<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Morocco Phase 1C.1 (docs/morocco-phase-1c1-generic-tax-foundation.md) -
 * the generic, country-neutral invoice-level tax breakdown recommended
 * as "Option B" in the Phase 1C audit. One row per distinct rate
 * actually used on an invoice, written by DocumentCalculationService via
 * InvoiceController/QuoteToInvoiceService whenever a (non-locked)
 * invoice's cart lines are saved.
 *
 * Deliberately NOT named/shaped after Spain or AEAT (no `impuesto`,
 * `clave_regimen`, `calificacion_operacion` columns like the existing,
 * VERI*FACTU-only `verifactu_record_tax_details` table has) - `rate`,
 * `taxable_base`, `tax_amount` are the only three concepts this phase
 * needs, and they mean the same thing for any country.
 *
 * Purely additive: `invoices.vta4/vta10/vta21` are NOT dropped or
 * altered by this migration (see the same migration's sibling doc for
 * the compatibility bridge) - this table is a new, independent source
 * of truth going forward, not a replacement of the legacy columns.
 *
 * No backfill: existing (already-issued) invoices get no rows here.
 * Rendering code must fall back to the pre-existing cart-derived
 * computation when no rows exist for an invoice - see
 * TemplateRendererService::render().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_tax_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id');
            $table->decimal('rate', 7, 3);
            $table->decimal('taxable_base', 15, 2);
            $table->decimal('tax_amount', 15, 2);
            $table->timestamps();

            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_tax_lines');
    }
};
