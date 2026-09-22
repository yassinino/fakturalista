<?php

namespace Tests\Unit;

use App\Exceptions\Verifactu\AeatSoapFaultException;
use App\Models\Verifactu\VerifactuSubmission;
use App\Services\Verifactu\Aeat\AeatResponseParser;
use Tests\TestCase;

/**
 * Pure parsing tests against literal AEAT response fixtures, modeled on
 * RespuestaSuministro.xsd's actual structure (docs/verifactu-xml-spec-freeze.md).
 * No DB, no HTTP - see docs/verifactu-aeat-connectivity.md §8.
 */
class AeatResponseParserTest extends TestCase
{
    private const NS_ENV = 'http://schemas.xmlsoap.org/soap/envelope/';
    private const NS_R = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/RespuestaSuministro.xsd';
    private const NS_SF = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd';

    private function parser(): AeatResponseParser
    {
        return new AeatResponseParser();
    }

    private function envelope(string $bodyInner): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="' . self::NS_ENV . '" xmlns:sfR="' . self::NS_R . '" xmlns:sf="' . self::NS_SF . '">
  <soapenv:Body>
    <sfR:RespuestaRegFactuSistemaFacturacion>' . $bodyInner . '</sfR:RespuestaRegFactuSistemaFacturacion>
  </soapenv:Body>
</soapenv:Envelope>';
    }

    /** @test */
    public function accepted_response_maps_to_accepted(): void
    {
        $xml = $this->envelope('
            <sfR:CSV>ABCD1234EFGH5678</sfR:CSV>
            <sfR:Cabecera><sf:ObligadoEmision><sf:NombreRazon>Test</sf:NombreRazon><sf:NIF>89890001K</sf:NIF></sf:ObligadoEmision></sfR:Cabecera>
            <sf:TiempoEsperaEnvio>60</sf:TiempoEsperaEnvio>
            <sfR:EstadoEnvio>Correcto</sfR:EstadoEnvio>
            <sfR:RespuestaLinea>
                <sf:IDFactura><sf:IDEmisorFactura>89890001K</sf:IDEmisorFactura><sf:NumSerieFactura>INV-1</sf:NumSerieFactura><sf:FechaExpedicionFactura>01-01-2026</sf:FechaExpedicionFactura></sf:IDFactura>
                <sfR:EstadoRegistro>Correcto</sfR:EstadoRegistro>
            </sfR:RespuestaLinea>
        ');

        $result = $this->parser()->parse($xml);

        $this->assertEquals('Correcto', $result->estadoEnvio);
        $this->assertEquals('Correcto', $result->estadoRegistro);
        $this->assertEquals('ABCD1234EFGH5678', $result->csv);
        $this->assertNull($result->errorCode);
        $this->assertFalse($result->isDuplicate());
        $this->assertEquals(VerifactuSubmission::STATUS_ACCEPTED, $result->mappedStatus());
        $this->assertEquals(60, $result->tiempoEsperaEnvio);
    }

    /** @test */
    public function accepted_with_errors_response_maps_correctly(): void
    {
        $xml = $this->envelope('
            <sfR:CSV>CSVACEPTCONERR</sfR:CSV>
            <sfR:Cabecera><sf:ObligadoEmision><sf:NombreRazon>Test</sf:NombreRazon><sf:NIF>89890001K</sf:NIF></sf:ObligadoEmision></sfR:Cabecera>
            <sfR:EstadoEnvio>ParcialmenteCorrecto</sfR:EstadoEnvio>
            <sfR:RespuestaLinea>
                <sf:IDFactura><sf:IDEmisorFactura>89890001K</sf:IDEmisorFactura><sf:NumSerieFactura>INV-1</sf:NumSerieFactura><sf:FechaExpedicionFactura>01-01-2026</sf:FechaExpedicionFactura></sf:IDFactura>
                <sfR:EstadoRegistro>AceptadoConErrores</sfR:EstadoRegistro>
                <sfR:CodigoErrorRegistro>1234</sfR:CodigoErrorRegistro>
                <sfR:DescripcionErrorRegistro>Advertencia menor</sfR:DescripcionErrorRegistro>
            </sfR:RespuestaLinea>
        ');

        $result = $this->parser()->parse($xml);

        $this->assertEquals('ParcialmenteCorrecto', $result->estadoEnvio);
        $this->assertEquals('AceptadoConErrores', $result->estadoRegistro);
        $this->assertEquals(1234, $result->errorCode);
        $this->assertEquals('Advertencia menor', $result->errorDescription);
        $this->assertEquals(VerifactuSubmission::STATUS_ACCEPTED_WITH_ERRORS, $result->mappedStatus());
    }

    /** @test */
    public function rejected_response_maps_to_rejected(): void
    {
        $xml = $this->envelope('
            <sfR:Cabecera><sf:ObligadoEmision><sf:NombreRazon>Test</sf:NombreRazon><sf:NIF>89890001K</sf:NIF></sf:ObligadoEmision></sfR:Cabecera>
            <sfR:EstadoEnvio>Incorrecto</sfR:EstadoEnvio>
            <sfR:RespuestaLinea>
                <sf:IDFactura><sf:IDEmisorFactura>89890001K</sf:IDEmisorFactura><sf:NumSerieFactura>INV-1</sf:NumSerieFactura><sf:FechaExpedicionFactura>01-01-2026</sf:FechaExpedicionFactura></sf:IDFactura>
                <sfR:EstadoRegistro>Incorrecto</sfR:EstadoRegistro>
                <sfR:CodigoErrorRegistro>9999</sfR:CodigoErrorRegistro>
                <sfR:DescripcionErrorRegistro>NIF no valido</sfR:DescripcionErrorRegistro>
            </sfR:RespuestaLinea>
        ');

        $result = $this->parser()->parse($xml);

        $this->assertEquals('Incorrecto', $result->estadoRegistro);
        $this->assertEquals(9999, $result->errorCode);
        $this->assertNull($result->csv);
        $this->assertEquals(VerifactuSubmission::STATUS_REJECTED, $result->mappedStatus());
    }

    /** @test */
    public function duplicate_response_is_flagged_and_carries_the_original_id_peticion(): void
    {
        $xml = $this->envelope('
            <sfR:Cabecera><sf:ObligadoEmision><sf:NombreRazon>Test</sf:NombreRazon><sf:NIF>89890001K</sf:NIF></sf:ObligadoEmision></sfR:Cabecera>
            <sfR:EstadoEnvio>Incorrecto</sfR:EstadoEnvio>
            <sfR:RespuestaLinea>
                <sf:IDFactura><sf:IDEmisorFactura>89890001K</sf:IDEmisorFactura><sf:NumSerieFactura>INV-1</sf:NumSerieFactura><sf:FechaExpedicionFactura>01-01-2026</sf:FechaExpedicionFactura></sf:IDFactura>
                <sfR:EstadoRegistro>Incorrecto</sfR:EstadoRegistro>
                <sf:RegistroDuplicado>
                    <sf:IdPeticionRegistroDuplicado>ORIGINAL-ID-PETICION-1</sf:IdPeticionRegistroDuplicado>
                    <sf:EstadoRegistroDuplicado>Correcta</sf:EstadoRegistroDuplicado>
                </sf:RegistroDuplicado>
            </sfR:RespuestaLinea>
        ');

        $result = $this->parser()->parse($xml);

        $this->assertTrue($result->isDuplicate());
        $this->assertEquals('ORIGINAL-ID-PETICION-1', $result->duplicateOfIdPeticion);
    }

    /** @test */
    public function soap_fault_throws_a_dedicated_exception(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="' . self::NS_ENV . '">
  <soapenv:Body>
    <soapenv:Fault>
      <faultcode>soapenv:Server</faultcode>
      <faultstring>Malformed request</faultstring>
    </soapenv:Fault>
  </soapenv:Body>
</soapenv:Envelope>';

        $this->expectException(AeatSoapFaultException::class);
        $this->expectExceptionMessage('Malformed request');

        $this->parser()->parse($xml);
    }

    /** @test */
    public function malformed_response_does_not_crash_and_yields_a_safe_default(): void
    {
        $result = $this->parser()->parse('<not-even-close-to-valid></not-even-close-to-valid>');

        $this->assertEquals('Incorrecto', $result->estadoEnvio);
        $this->assertNull($result->estadoRegistro);
    }

    /** @test */
    public function raw_response_xml_is_preserved_verbatim_for_audit(): void
    {
        $xml = $this->envelope('
            <sfR:Cabecera><sf:ObligadoEmision><sf:NombreRazon>Test</sf:NombreRazon><sf:NIF>89890001K</sf:NIF></sf:ObligadoEmision></sfR:Cabecera>
            <sfR:EstadoEnvio>Correcto</sfR:EstadoEnvio>
        ');

        $result = $this->parser()->parse($xml);

        $this->assertEquals($xml, $result->rawResponseXml);
    }
}
