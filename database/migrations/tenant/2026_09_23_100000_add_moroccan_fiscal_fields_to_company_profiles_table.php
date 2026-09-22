<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Morocco Phase 1B (docs/morocco-phase-1b-identity.md). Two new, purely
 * additive identity fields for the ISSUER (tenant's own company):
 *
 * - `ice`: Identifiant Commun de l'Entreprise - Morocco's primary business
 *   identifier, mirroring the column already added to `customers` in an
 *   earlier phase (2025_11_05_123232_add_ice_to_customers_name.php).
 * - `if_number`: Identifiant Fiscal - Morocco's own tax-administration
 *   identifier. Named distinctly from `tax_id` (Spanish NIF/CIF) and
 *   `vat_number` (EU VAT format) - it is not the same instrument as
 *   either, so it does not reuse those columns.
 *
 * Morocco's "Registre de Commerce (RC)" reuses the EXISTING
 * `registration_number` column instead of adding a third one - that
 * column already holds "RM Madrid T-12345"-style Spanish Registro
 * Mercantil numbers (see settings.vue's `settings.tax.regName` field,
 * unused by any backend logic beyond storage/display) and RC is the
 * same underlying concept (an official commercial-registry number),
 * just labeled differently per country in the UI.
 *
 * Both nullable, no default, no format/checksum validation - Moroccan
 * ICE/IF format was not verified against a primary legal source in this
 * phase (see docs/morocco-phase-1b-identity.md "legal assumptions
 * deliberately NOT made").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->string('ice')->nullable()->after('registration_number');
            $table->string('if_number')->nullable()->after('ice');
        });
    }

    public function down(): void
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->dropColumn(['ice', 'if_number']);
        });
    }
};
