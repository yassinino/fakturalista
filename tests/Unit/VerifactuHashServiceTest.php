<?php

namespace Tests\Unit;

use App\Services\Verifactu\VerifactuHashService;
use Tests\TestCase;

/**
 * VerifactuHashService against the three official worked examples from
 * AEAT "Detalle de las especificaciones técnicas para generación de la
 * huella o hash de los registros de facturación", v0.1.2 §annex - see
 * docs/verifactu-implementation-plan.md §0/§7.5, where these exact input
 * strings and hash outputs were independently reproduced in Python
 * before this PHP implementation was written.
 *
 * These are a correctness gate, not ordinary unit tests: any refactor
 * that changes a byte of the concatenated input or the hash output
 * breaks interoperability with AEAT, full stop.
 */
class VerifactuHashServiceTest extends TestCase
{
    private function service(): VerifactuHashService
    {
        return new VerifactuHashService();
    }

    /** @test */
    public function official_vector_1_first_alta_record_with_no_previous_hash(): void
    {
        $service = $this->service();

        $fields = [
            'id_emisor_factura'            => '89890001K',
            'num_serie_factura'            => '12345678/G33',
            'fecha_expedicion_factura'     => '01-01-2024',
            'tipo_factura'                 => 'F1',
            'cuota_total'                  => 12.35,
            'importe_total'                => 123.45,
            'huella_registro_anterior'     => null,
            'fecha_hora_huso_gen_registro' => '2024-01-01T19:20:30+01:00',
        ];

        $expectedInput = 'IDEmisorFactura=89890001K&NumSerieFactura=12345678/G33&FechaExpedicionFactura=01-01-2024'
            . '&TipoFactura=F1&CuotaTotal=12.35&ImporteTotal=123.45&Huella='
            . '&FechaHoraHusoGenRegistro=2024-01-01T19:20:30+01:00';

        $this->assertEquals($expectedInput, $service->buildAltaInput($fields));
        $this->assertEquals(
            '3C464DAF61ACB827C65FDA19F352A4E3BDC2C640E9E9FC4CC058073F38F12F60',
            $service->hashAlta($fields)
        );
    }

    /** @test */
    public function official_vector_2_second_alta_record_chained_to_vector_1(): void
    {
        $service = $this->service();

        $fields = [
            'id_emisor_factura'            => '89890001K',
            'num_serie_factura'            => '12345679/G34',
            'fecha_expedicion_factura'     => '01-01-2024',
            'tipo_factura'                 => 'F1',
            'cuota_total'                  => 12.35,
            'importe_total'                => 123.45,
            'huella_registro_anterior'     => '3C464DAF61ACB827C65FDA19F352A4E3BDC2C640E9E9FC4CC058073F38F12F60',
            'fecha_hora_huso_gen_registro' => '2024-01-01T19:20:35+01:00',
        ];

        $this->assertEquals(
            'F7B94CFD8924EDFF273501B01EE5153E4CE8F259766F88CF6ACB8935802A2B97',
            $service->hashAlta($fields)
        );
    }

    /** @test */
    public function official_vector_3_anulacion_chained_to_vector_2(): void
    {
        $service = $this->service();

        $fields = [
            'id_emisor_factura'            => '89890001K',
            'num_serie_factura'            => '12345679/G34',
            'fecha_expedicion_factura'     => '01-01-2024',
            'huella_registro_anterior'     => 'F7B94CFD8924EDFF273501B01EE5153E4CE8F259766F88CF6ACB8935802A2B97',
            'fecha_hora_huso_gen_registro' => '2024-01-01T19:20:40+01:00',
        ];

        $expectedInput = 'IDEmisorFacturaAnulada=89890001K&NumSerieFacturaAnulada=12345679/G34'
            . '&FechaExpedicionFacturaAnulada=01-01-2024'
            . '&Huella=F7B94CFD8924EDFF273501B01EE5153E4CE8F259766F88CF6ACB8935802A2B97'
            . '&FechaHoraHusoGenRegistro=2024-01-01T19:20:40+01:00';

        $this->assertEquals($expectedInput, $service->buildAnulacionInput($fields));
        $this->assertEquals(
            '177547C0D57AC74748561D054A9CEC14B4C4EA23D1BEFD6F2E69E3A388F90C68',
            $service->hashAnulacion($fields)
        );
    }

    // ── Normalization rules (S6 §3), independent of the vectors above ──

    /** @test */
    public function amounts_with_different_decimal_precision_normalize_identically(): void
    {
        $service = $this->service();

        $this->assertEquals('123.10', $service->normalizeAmount(123.1));
        $this->assertEquals('123.10', $service->normalizeAmount('123.10'));
        $this->assertEquals('0.00', $service->normalizeAmount(0));
    }

    /** @test */
    public function values_are_trimmed_before_concatenation(): void
    {
        $service = $this->service();

        $input = $service->buildAltaInput([
            'id_emisor_factura'            => '  89890001K  ',
            'num_serie_factura'            => '12345678/G33',
            'fecha_expedicion_factura'     => '01-01-2024',
            'tipo_factura'                 => 'F1',
            'cuota_total'                  => 12.35,
            'importe_total'                => 123.45,
            'huella_registro_anterior'     => null,
            'fecha_hora_huso_gen_registro' => '2024-01-01T19:20:30+01:00',
        ]);

        $this->assertStringStartsWith('IDEmisorFactura=89890001K&', $input);
    }

    /** @test */
    public function hash_output_is_64_char_uppercase_hex(): void
    {
        $hash = $this->service()->hash('anything');

        $this->assertSame(64, strlen($hash));
        $this->assertSame(strtoupper($hash), $hash);
        $this->assertMatchesRegularExpression('/^[0-9A-F]{64}$/', $hash);
    }
}
