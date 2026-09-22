<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2C: building the actual RegistroAlta/RegistroAnulacion XML surfaced
 * several fields the AEAT schema requires that Phase 2B's snapshot didn't
 * capture. Each is a genuine gap, not a guess:
 *
 * - NombreRazonEmisor / Destinatario NombreRazon+NIF come from
 *   CompanyProfile/Customer, which are MUTABLE (a company can rename
 *   itself, a customer's details can be edited) - the regulatory record
 *   must keep representing what was true at generation time, so these are
 *   copied in once, by VerifactuChainService, never re-read from Invoice/
 *   Customer/CompanyProfile by the XML builder.
 * - DescripcionOperacion (AEAT-mandatory) has no source anywhere in
 *   Fakturalista today (Invoice.descripcion_operacion exists as a column
 *   since Phase 2A but nothing ever populates it) - VerifactuChainService
 *   now requires it to be set before generating an alta record, rather
 *   than inventing text.
 * - TipoRectificativa is a snapshot of Invoice.rectification_type (S/I),
 *   copied for the same "don't re-read mutable/derived state later" reason
 *   even though rectification_type is not expected to change post-issuance.
 * - previous_verifactu_record_id lets the XML builder read the PREVIOUS
 *   record's own already-immutable nif_emisor/serie_numero/fecha_expedicion/
 *   huella for Encadenamiento/RegistroAnterior, instead of only having its
 *   hash (huella_registro_anterior, from Phase 2B) without the rest of its
 *   identity.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verifactu_records', function (Blueprint $table) {
            $table->string('nombre_razon_emisor', 120)->nullable()->after('nif_emisor');
            $table->string('descripcion_operacion', 500)->nullable()->after('importe_total');
            $table->string('destinatario_nombre_razon', 120)->nullable()->after('descripcion_operacion');
            $table->string('destinatario_nif', 9)->nullable()->after('destinatario_nombre_razon');
            $table->char('tipo_rectificativa', 1)->nullable()->after('destinatario_nif');
            // Snapshot of company_profiles.verifactu_installation_number
            // at generation time - per-tenant config, still mutable, so
            // treated the same as nombre_razon_emisor above.
            $table->string('numero_instalacion', 100)->nullable()->after('tipo_rectificativa');
            $table->unsignedBigInteger('previous_verifactu_record_id')->nullable()->after('huella_registro_anterior');

            $table->foreign('previous_verifactu_record_id', 'verifactu_records_previous_record_foreign')
                ->references('id')->on('verifactu_records')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('verifactu_records', function (Blueprint $table) {
            $table->dropForeign('verifactu_records_previous_record_foreign');
            $table->dropColumn([
                'nombre_razon_emisor',
                'descripcion_operacion',
                'destinatario_nombre_razon',
                'destinatario_nif',
                'tipo_rectificativa',
                'numero_instalacion',
                'previous_verifactu_record_id',
            ]);
        });
    }
};
