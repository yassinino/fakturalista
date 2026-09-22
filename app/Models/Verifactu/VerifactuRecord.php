<?php

namespace App\Models\Verifactu;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A single VERI*FACTU "registro de facturación" (alta or anulación).
 *
 * Immutable by design: RD 1007/2023 art. 8.2 requires invoicing records to
 * be inalterables. delete() is overridden to throw rather than relying on
 * SoftDeletes (which would still permit "removal" from the app's point of
 * view) or on callers simply not calling it.
 */
class VerifactuRecord extends Model
{
    public const TIPO_ALTA      = 'alta';
    public const TIPO_ANULACION = 'anulacion';

    public const TIPOS_REGISTRO = [self::TIPO_ALTA, self::TIPO_ANULACION];

    // Only 'pendiente' is ever written as of Phase 2B - no submission path
    // exists yet (Phase 2D). The other states are declared here because
    // they are part of this column's intended domain, documented in
    // docs/verifactu-implementation-plan.md §10.
    public const ESTADO_PENDIENTE            = 'pendiente';
    public const ESTADO_ENVIANDO             = 'enviando';
    public const ESTADO_ACEPTADO             = 'aceptado';
    public const ESTADO_ACEPTADO_CON_ERRORES = 'aceptado_con_errores';
    public const ESTADO_RECHAZADO            = 'rechazado';
    public const ESTADO_PENDIENTE_REINTENTO  = 'pendiente_reintento';

    protected $table = 'verifactu_records';

    protected $fillable = [
        'invoice_id',
        'tipo_registro',
        'nif_emisor',
        'nombre_razon_emisor',
        'serie_numero',
        'fecha_expedicion',
        'tipo_factura',
        'descripcion_operacion',
        'destinatario_nombre_razon',
        'destinatario_nif',
        'destinatario_id_pais',
        'destinatario_id_type',
        'destinatario_id',
        'tipo_rectificativa',
        'rectifica_num_serie',
        'rectifica_fecha_expedicion',
        'importe_rectificacion_base',
        'importe_rectificacion_cuota',
        'numero_instalacion',
        'cuota_total',
        'importe_total',
        'huella_registro_anterior',
        'previous_verifactu_record_id',
        'es_primer_registro',
        'fecha_hora_huso_gen_registro',
        'huella',
        'estado_envio',
    ];

    protected $casts = [
        'fecha_expedicion'            => 'date',
        'rectifica_fecha_expedicion'  => 'date',
        'cuota_total'                 => 'decimal:2',
        'importe_total'               => 'decimal:2',
        'importe_rectificacion_base'  => 'decimal:2',
        'importe_rectificacion_cuota' => 'decimal:2',
        'es_primer_registro'          => 'boolean',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * The immediately-previous record in this NIF's chain (of either type -
     * alta or anulación), if any. Used by VerifactuXmlBuilder to build
     * Encadenamiento/RegistroAnterior from another already-immutable
     * VerifactuRecord row, never from mutable Invoice/Customer data.
     */
    public function previousRecord(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_verifactu_record_id');
    }

    /**
     * Desglose/DetalleDesglose rows - alta records only (see
     * VerifactuRecordTaxDetail).
     */
    public function taxDetails(): HasMany
    {
        return $this->hasMany(VerifactuRecordTaxDetail::class);
    }

    public function isAlta(): bool
    {
        return $this->tipo_registro === self::TIPO_ALTA;
    }

    public function isAnulacion(): bool
    {
        return $this->tipo_registro === self::TIPO_ANULACION;
    }

    /**
     * @throws \RuntimeException always - see class docblock.
     */
    public function delete()
    {
        throw new \RuntimeException(
            'Los registros VERI*FACTU no se pueden eliminar: son un rastro de auditoría inalterable (RD 1007/2023 art. 8.2).'
        );
    }
}
