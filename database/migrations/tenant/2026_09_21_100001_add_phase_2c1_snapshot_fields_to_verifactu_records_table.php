<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2C.1 closes two remaining snapshot gaps found during Phase 2C:
 *
 * 1. Destinatario IDOtro (foreign recipient) - Phase 2C only supported
 *    the NIF branch. Mirrors the customers-table migration: a type code
 *    (lista L7) + the ID value, plus the country when known.
 *
 * 2. Rectified-invoice identity + ImporteRectificacion - Phase 2C's
 *    VerifactuXmlBuilder read $invoice->rectifies directly (a documented,
 *    but now removed, exception to "never read mutable data at build
 *    time"). These columns let the ORIGINAL invoice's identity, and (for
 *    TipoRectificativa=S only) its own base/cuota, be captured once, at
 *    the rectification's own record-generation time, from that original
 *    invoice's own fields - which Phase 2A already guarantees are frozen
 *    once issued, but which are still a separate live Eloquent model the
 *    XML builder should never need to load again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verifactu_records', function (Blueprint $table) {
            $table->string('destinatario_id_pais', 2)->nullable()->after('destinatario_nif');
            $table->string('destinatario_id_type', 2)->nullable()->after('destinatario_id_pais');
            $table->string('destinatario_id', 20)->nullable()->after('destinatario_id_type');

            // FacturasRectificadas/IDFacturaRectificada - the ORIGINAL
            // invoice's own series/date, snapshotted once. No separate
            // "rectifica_id_emisor" column: IDFacturaARType's own
            // IDEmisorFactura is, per the XSD's annotation, derived by
            // AEAT from the current record's own IDFactura block, i.e.
            // it's always identical to this same row's nif_emisor.
            $table->string('rectifica_num_serie', 60)->nullable()->after('tipo_rectificativa');
            $table->date('rectifica_fecha_expedicion')->nullable()->after('rectifica_num_serie');

            // ImporteRectificacion (DesgloseRectificacionType) - only
            // populated for TipoRectificativa=S (sustitución). Null for
            // 'I' (por diferencias) and for non-rectificative invoices.
            $table->decimal('importe_rectificacion_base', 15, 2)->nullable()->after('rectifica_fecha_expedicion');
            $table->decimal('importe_rectificacion_cuota', 15, 2)->nullable()->after('importe_rectificacion_base');
        });
    }

    public function down(): void
    {
        Schema::table('verifactu_records', function (Blueprint $table) {
            $table->dropColumn([
                'destinatario_id_pais',
                'destinatario_id_type',
                'destinatario_id',
                'rectifica_id_emisor',
                'rectifica_num_serie',
                'rectifica_fecha_expedicion',
                'importe_rectificacion_base',
                'importe_rectificacion_cuota',
            ]);
        });
    }
};
