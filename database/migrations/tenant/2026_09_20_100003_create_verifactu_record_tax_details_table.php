<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The per-rate Desglose/DetalleDesglose breakdown AEAT requires
 * (SuministroInformacion.xsd DetalleType) - mandatory on every alta
 * record, at least one row. Fakturalista has no per-rate BASE amount
 * stored anywhere (only the per-rate CUOTA, on invoices.vta4/vta10/vta21),
 * so this is computed once, at record-generation time
 * (VerifactuChainService::recordAlta()), from those already-stored,
 * already-authoritative cuota columns - base = cuota / (rate/100), the
 * exact algebraic inverse of how the cuota was computed in the first
 * place, not a new/different fiscal calculation. Never derived from
 * `carts` - see the class docblock on VerifactuChainService.
 *
 * Not used by anulación records (RegistroFacturacionAnulacionType has no
 * Desglose element).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verifactu_record_tax_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('verifactu_record_id');

            // Orden HAC/1177/2024 code lists (SuministroInformacion.xsd):
            // impuesto: ImpuestoType ("01" = IVA - the only tax Fakturalista
            // supports; IPSI/IGIC for Ceuta/Melilla/Canarias are out of
            // scope, matching CompanyProfile.country_code defaulting to
            // mainland/Balearic "ES" throughout the app).
            $table->string('impuesto', 2)->default('01');
            // clave_regimen: IdOperacionesTrascendenciaTributariaType
            // ("01" = régimen general - the only regime Fakturalista's UI
            // currently supports; no special-regime selection exists).
            $table->string('clave_regimen', 2)->default('01');
            // calificacion_operacion: CalificacionOperacionType ("S1" =
            // sujeta y no exenta, sin inversión del sujeto pasivo - the
            // only case Fakturalista currently models; no exemption/
            // reverse-charge flag exists anywhere in the app, per the
            // Phase 1 gap analysis).
            $table->string('calificacion_operacion', 2)->nullable()->default('S1');
            $table->string('operacion_exenta', 2)->nullable();

            $table->decimal('tipo_impositivo', 5, 2);
            $table->decimal('base_imponible', 15, 2);
            $table->decimal('cuota_repercutida', 15, 2)->nullable();

            $table->timestamps();

            $table->foreign('verifactu_record_id')->references('id')->on('verifactu_records')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verifactu_record_tax_details');
    }
};
