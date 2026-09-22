<?php

namespace App\Models\Verifactu;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One Desglose/DetalleDesglose row (SuministroInformacion.xsd DetalleType)
 * for an alta VerifactuRecord. See the migration docblock for how these
 * are derived (algebraically, from Invoice.vta4/vta10/vta21 - never from
 * mutable cart lines) and why.
 */
class VerifactuRecordTaxDetail extends Model
{
    protected $table = 'verifactu_record_tax_details';

    protected $fillable = [
        'verifactu_record_id',
        'impuesto',
        'clave_regimen',
        'calificacion_operacion',
        'operacion_exenta',
        'tipo_impositivo',
        'base_imponible',
        'cuota_repercutida',
    ];

    protected $casts = [
        'tipo_impositivo'   => 'decimal:2',
        'base_imponible'    => 'decimal:2',
        'cuota_repercutida' => 'decimal:2',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(VerifactuRecord::class, 'verifactu_record_id');
    }
}
