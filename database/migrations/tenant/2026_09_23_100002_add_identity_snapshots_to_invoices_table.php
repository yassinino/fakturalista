<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Morocco Phase 1B (docs/morocco-phase-1b-identity.md §7). A generic,
 * country-agnostic immutability layer that did NOT exist before this
 * migration: `invoices` had no snapshot of the seller/customer identity
 * at all - the PDF always re-read the LIVE `company_profiles`/`customers`
 * rows. That's a real gap for every tenant (Spain included), not just
 * Morocco: renaming a company or editing a customer today silently
 * rewrites the identity shown on every past invoice.
 *
 * Deliberately NOT coupled to VERI*FACTU: `verifactu_records` already has
 * its own, separate snapshot fields (2026_09_20_100002_add_xml_snapshot_fields...)
 * used only for the AEAT submission XML. This is the ordinary invoice's
 * own snapshot, used for rendering (PDF/admin display), and exists
 * whether or not the tenant ever touches VERI*FACTU.
 *
 * JSON, not individual columns: identity fields differ by country
 * (ICE/IF/RC for Morocco, NIF/VAT/Registro Mercantil for Spain) and are
 * expected to grow in Phase 1C - a single JSON blob per side avoids a
 * new migration every time a country needs one more identity field.
 *
 * Nullable, no backfill: existing already-issued invoices get NULL here
 * (no snapshot ever existed for them) - rendering code must fall back to
 * live company/customer data for those, exactly as it always has. This
 * migration only changes behavior for invoices issued AFTER it runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->json('company_snapshot')->nullable()->after('descripcion_operacion');
            $table->json('customer_snapshot')->nullable()->after('company_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['company_snapshot', 'customer_snapshot']);
        });
    }
};
