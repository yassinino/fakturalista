<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2C.1: Customer.tax_id only ever represented a Spanish NIF/CIF/NIE.
 * A customer identified in a foreign scheme (per AEAT's IDOtro structure,
 * SuministroInformacion.xsd PersonaFisicaJuridicaType) needs a document
 * TYPE (Orden HAC/1177/2024, Anexo, lista L7) alongside the ID value
 * itself - a single extra string column can't represent that safely.
 *
 * The customer's country for AEAT's IDOtro/CodigoPais is deliberately NOT
 * duplicated here - it's already derivable from the existing
 * `billing_country_id` -> countries.code (already ISO 3166-1 alpha-2, see
 * database/seeders/CountrySeeder.php).
 *
 * Purely additive: existing customers get NULL in both new columns and
 * keep resolving via the existing `tax_id` (Spanish NIF) path exactly as
 * before - see VerifactuChainService::resolveDestinatario().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Orden HAC/1177/2024 Anexo, lista L7 code (02 NIF-IVA, 03
            // Pasaporte, 04 documento oficial del país de residencia, 05
            // certificado de residencia, 06 otro documento probatorio, 07
            // no censado). Null unless this customer is explicitly
            // identified as foreign.
            $table->string('foreign_tax_id_type', 2)->nullable()->after('tax_id');
            $table->string('foreign_tax_id', 20)->nullable()->after('foreign_tax_id_type');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['foreign_tax_id_type', 'foreign_tax_id']);
        });
    }
};
