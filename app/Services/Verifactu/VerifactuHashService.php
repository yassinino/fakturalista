<?php

namespace App\Services\Verifactu;

/**
 * Pure implementation of the AEAT hash ("huella") specification for
 * VERI*FACTU invoicing records - no I/O, no DB, no Eloquent. Every rule
 * below is sourced from AEAT "Detalle de las especificaciones técnicas
 * para generación de la huella o hash de los registros de facturación",
 * v0.1.2 (27/08/2024) §2-§5 - see docs/verifactu-implementation-plan.md
 * §0 (S6) and §7 for the full citation and the three official worked
 * examples this class is tested against byte-for-byte
 * (tests/Unit/VerifactuHashServiceTest.php).
 *
 * Rules implemented here (S6 §3, verbatim requirements):
 *  - SHA-256, output as 64-char uppercase hex (S6 §5).
 *  - Concatenate as "campo1=valor1&campo2=valor2&...", plain & / =
 *    separators, NOT URL-encoded.
 *  - Trim leading/trailing whitespace from every value.
 *  - Amounts are normalized to a fixed 2-decimal string before hashing
 *    (so "123.1" and "123.10" hash identically) - never hash a raw DB
 *    string representation directly.
 *  - A field with no value still appears as "campoN=" (name + "=",
 *    nothing after) - this is how the first record's empty Huella is
 *    represented.
 *  - Encode the final string as UTF-8 bytes before hashing.
 */
class VerifactuHashService
{
    /**
     * @param array{
     *     id_emisor_factura: string,
     *     num_serie_factura: string,
     *     fecha_expedicion_factura: string,
     *     tipo_factura: string,
     *     cuota_total: float|string,
     *     importe_total: float|string,
     *     huella_registro_anterior: ?string,
     *     fecha_hora_huso_gen_registro: string,
     * } $fields
     */
    public function hashAlta(array $fields): string
    {
        return $this->hash($this->buildAltaInput($fields));
    }

    /**
     * @param array{
     *     id_emisor_factura: string,
     *     num_serie_factura: string,
     *     fecha_expedicion_factura: string,
     *     huella_registro_anterior: ?string,
     *     fecha_hora_huso_gen_registro: string,
     * } $fields
     */
    public function hashAnulacion(array $fields): string
    {
        return $this->hash($this->buildAnulacionInput($fields));
    }

    /**
     * The exact concatenated input string for an alta record (S6 §3, field
     * order: IDEmisorFactura, NumSerieFactura, FechaExpedicionFactura,
     * TipoFactura, CuotaTotal, ImporteTotal, Huella (of previous record),
     * FechaHoraHusoGenRegistro). Exposed publicly so tests can assert the
     * exact string, not just its hash.
     */
    public function buildAltaInput(array $fields): string
    {
        return $this->concatenate([
            'IDEmisorFactura'          => $fields['id_emisor_factura'],
            'NumSerieFactura'          => $fields['num_serie_factura'],
            'FechaExpedicionFactura'   => $fields['fecha_expedicion_factura'],
            'TipoFactura'              => $fields['tipo_factura'],
            'CuotaTotal'               => $this->normalizeAmount($fields['cuota_total']),
            'ImporteTotal'             => $this->normalizeAmount($fields['importe_total']),
            'Huella'                   => $fields['huella_registro_anterior'] ?? '',
            'FechaHoraHusoGenRegistro' => $fields['fecha_hora_huso_gen_registro'],
        ]);
    }

    /**
     * The exact concatenated input string for an anulación record (S6 §3,
     * field order: IDEmisorFacturaAnulada, NumSerieFacturaAnulada,
     * FechaExpedicionFacturaAnulada, Huella (of previous record),
     * FechaHoraHusoGenRegistro). Note this has NO TipoFactura/CuotaTotal/
     * ImporteTotal - those are alta-only fields.
     */
    public function buildAnulacionInput(array $fields): string
    {
        return $this->concatenate([
            'IDEmisorFacturaAnulada'        => $fields['id_emisor_factura'],
            'NumSerieFacturaAnulada'        => $fields['num_serie_factura'],
            'FechaExpedicionFacturaAnulada' => $fields['fecha_expedicion_factura'],
            'Huella'                        => $fields['huella_registro_anterior'] ?? '',
            'FechaHoraHusoGenRegistro'      => $fields['fecha_hora_huso_gen_registro'],
        ]);
    }

    public function hash(string $input): string
    {
        return strtoupper(hash('sha256', mb_convert_encoding($input, 'UTF-8', 'UTF-8')));
    }

    /**
     * Fixed 2-decimal-place normalization so "123.1" and "123.10" always
     * hash identically (S6 §3).
     */
    public function normalizeAmount(float|string $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function concatenate(array $fields): string
    {
        $parts = [];
        foreach ($fields as $name => $value) {
            $parts[] = $name . '=' . trim((string) $value);
        }

        return implode('&', $parts);
    }
}
