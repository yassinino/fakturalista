<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per VERI*FACTU "registro de facturación" (alta or anulación),
 * per Orden HAC/1177/2024 art. 7/13 and the hash spec (AEAT
 * "especificaciones técnicas ... huella", v0.1.2). Phase 2B scope only:
 * this table stores the hash-input snapshot and the resulting huella so
 * the chain can be built and independently re-verified. It intentionally
 * has NO xml_generado/id_peticion/csv/codigo_error/response_raw columns
 * yet - those belong to the XML (Phase 2C) and AEAT-submission (Phase 2D)
 * work, not to this one.
 *
 * No soft deletes, and VerifactuRecord::delete() is overridden to throw:
 * RD 1007/2023 art. 8.2 requires these records be inalterables - an
 * apparently-safe soft-delete here would still violate that.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verifactu_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id');
            $table->string('tipo_registro', 20); // 'alta' | 'anulacion'
            $table->string('nif_emisor', 20);
            $table->string('serie_numero'); // snapshot of Invoice.reference at hash time
            $table->date('fecha_expedicion');

            // Alta-only hash inputs (null for anulacion records - see
            // VerifactuHashService, which builds a different field set
            // per §7.2 of docs/verifactu-implementation-plan.md).
            $table->string('tipo_factura', 2)->nullable();
            $table->decimal('cuota_total', 15, 2)->nullable();
            $table->decimal('importe_total', 15, 2)->nullable();

            $table->string('huella_registro_anterior', 64)->nullable();
            $table->boolean('es_primer_registro')->default(false);
            // Stored as the exact string used in the hash input (ISO-8601
            // with UTC offset) - not a datetime column, to avoid any
            // driver/timezone reformatting altering the byte-for-byte
            // value that was actually hashed.
            $table->string('fecha_hora_huso_gen_registro', 40);

            $table->string('huella', 64);
            $table->string('estado_envio', 30)->default('pendiente');

            $table->timestamps();

            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('restrict');
            // At most one alta and one anulacion record per invoice - a
            // DB-level backstop against a bug generating duplicates.
            $table->unique(['invoice_id', 'tipo_registro']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verifactu_records');
    }
};
