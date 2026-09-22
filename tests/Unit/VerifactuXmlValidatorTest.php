<?php

namespace Tests\Unit;

use App\Exceptions\Verifactu\VerifactuXmlValidationException;
use App\Services\Verifactu\VerifactuXmlValidator;
use Tests\TestCase;

/**
 * Pure schema-validation tests against literal XML fixtures - no DB, no
 * Eloquent. The "valid" fixture is modeled directly on AEAT's own worked
 * example (docs/verifactu-xml-spec-freeze.md, D1 §9.1.1.1), with clearly
 * fictional identities (see the class docblock's data-security note).
 */
class VerifactuXmlValidatorTest extends TestCase
{
    private const NS = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd';

    private function validator(): VerifactuXmlValidator
    {
        return new VerifactuXmlValidator();
    }

    private function validAltaXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<RegistroAlta xmlns="' . self::NS . '">
   <IDVersion>1.0</IDVersion>
   <IDFactura>
      <IDEmisorFactura>89890001K</IDEmisorFactura>
      <NumSerieFactura>TEST-0001</NumSerieFactura>
      <FechaExpedicionFactura>13-09-2024</FechaExpedicionFactura>
   </IDFactura>
   <NombreRazonEmisor>Empresa Ficticia SL</NombreRazonEmisor>
   <TipoFactura>F1</TipoFactura>
   <DescripcionOperacion>Servicios de consultoría de prueba</DescripcionOperacion>
   <Desglose>
      <DetalleDesglose>
         <ClaveRegimen>01</ClaveRegimen>
         <CalificacionOperacion>S1</CalificacionOperacion>
         <TipoImpositivo>21</TipoImpositivo>
         <BaseImponibleOimporteNoSujeto>100.00</BaseImponibleOimporteNoSujeto>
         <CuotaRepercutida>21.00</CuotaRepercutida>
      </DetalleDesglose>
   </Desglose>
   <CuotaTotal>21.00</CuotaTotal>
   <ImporteTotal>121.00</ImporteTotal>
   <Encadenamiento>
      <PrimerRegistro>S</PrimerRegistro>
   </Encadenamiento>
   <SistemaInformatico>
      <NombreRazon>Fakturalista Test</NombreRazon>
      <NIF>B00000000</NIF>
      <NombreSistemaInformatico>Fakturalista</NombreSistemaInformatico>
      <IdSistemaInformatico>FK</IdSistemaInformatico>
      <Version>1.0.0-test</Version>
      <NumeroInstalacion>TEST-INSTALL-1</NumeroInstalacion>
      <TipoUsoPosibleSoloVerifactu>S</TipoUsoPosibleSoloVerifactu>
      <TipoUsoPosibleMultiOT>S</TipoUsoPosibleMultiOT>
      <IndicadorMultiplesOT>N</IndicadorMultiplesOT>
   </SistemaInformatico>
   <FechaHoraHusoGenRegistro>2024-09-13T19:20:30+01:00</FechaHoraHusoGenRegistro>
   <TipoHuella>01</TipoHuella>
   <Huella>3C464DAF61ACB827C65FDA19F352A4E3BDC2C640E9E9FC4CC058073F38F12F60</Huella>
</RegistroAlta>';
    }

    private function validAnulacionXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<RegistroAnulacion xmlns="' . self::NS . '">
   <IDVersion>1.0</IDVersion>
   <IDFactura>
      <IDEmisorFacturaAnulada>89890001K</IDEmisorFacturaAnulada>
      <NumSerieFacturaAnulada>TEST-0001</NumSerieFacturaAnulada>
      <FechaExpedicionFacturaAnulada>13-09-2024</FechaExpedicionFacturaAnulada>
   </IDFactura>
   <Encadenamiento>
      <RegistroAnterior>
         <IDEmisorFactura>89890001K</IDEmisorFactura>
         <NumSerieFactura>TEST-0000</NumSerieFactura>
         <FechaExpedicionFactura>12-09-2024</FechaExpedicionFactura>
         <Huella>3C464DAF61ACB827C65FDA19F352A4E3BDC2C640E9E9FC4CC058073F38F12F60</Huella>
      </RegistroAnterior>
   </Encadenamiento>
   <SistemaInformatico>
      <NombreRazon>Fakturalista Test</NombreRazon>
      <NIF>B00000000</NIF>
      <NombreSistemaInformatico>Fakturalista</NombreSistemaInformatico>
      <IdSistemaInformatico>FK</IdSistemaInformatico>
      <Version>1.0.0-test</Version>
      <NumeroInstalacion>TEST-INSTALL-1</NumeroInstalacion>
      <TipoUsoPosibleSoloVerifactu>S</TipoUsoPosibleSoloVerifactu>
      <TipoUsoPosibleMultiOT>S</TipoUsoPosibleMultiOT>
      <IndicadorMultiplesOT>N</IndicadorMultiplesOT>
   </SistemaInformatico>
   <FechaHoraHusoGenRegistro>2024-09-13T19:25:00+01:00</FechaHoraHusoGenRegistro>
   <TipoHuella>01</TipoHuella>
   <Huella>177547C0D57AC74748561D054A9CEC14B4C4EA23D1BEFD6F2E69E3A388F90C68</Huella>
</RegistroAnulacion>';
    }

    /** @test */
    public function valid_alta_document_passes(): void
    {
        $this->assertTrue($this->validator()->isValid($this->validAltaXml()));
        $this->validator()->validate($this->validAltaXml());
        $this->addToAssertionCount(1); // no exception thrown
    }

    /** @test */
    public function valid_anulacion_document_passes(): void
    {
        $this->assertTrue($this->validator()->isValid($this->validAnulacionXml()));
    }

    /** @test */
    public function document_missing_a_mandatory_field_fails_with_a_useful_message(): void
    {
        $xml = str_replace('<DescripcionOperacion>Servicios de consultoría de prueba</DescripcionOperacion>', '', $this->validAltaXml());

        $this->assertFalse($this->validator()->isValid($xml));

        try {
            $this->validator()->validate($xml);
            $this->fail('Expected VerifactuXmlValidationException was not thrown.');
        } catch (VerifactuXmlValidationException $e) {
            $this->assertNotEmpty($e->getValidationErrors());
            // A useful, human-readable message - not a raw LibXMLError object.
            $this->assertIsString($e->getValidationErrors()[0]);
            $this->assertStringContainsString('DescripcionOperacion', $e->getMessage());
        }
    }

    /** @test */
    public function document_with_an_invalid_enum_value_fails(): void
    {
        $xml = str_replace('<TipoFactura>F1</TipoFactura>', '<TipoFactura>X9</TipoFactura>', $this->validAltaXml());

        $this->assertFalse($this->validator()->isValid($xml));
    }

    /** @test */
    public function malformed_nif_wrong_length_fails_validation(): void
    {
        // NIFType requires EXACTLY 9 characters.
        $xml = str_replace('<IDEmisorFactura>89890001K</IDEmisorFactura>', '<IDEmisorFactura>123</IDEmisorFactura>', $this->validAltaXml());

        $this->assertFalse($this->validator()->isValid($xml));
    }

    /** @test */
    public function not_well_formed_xml_fails_gracefully_without_throwing_an_unrelated_error(): void
    {
        $xml = '<RegistroAlta><Unclosed>';

        $this->assertFalse($this->validator()->isValid($xml));

        $this->expectException(VerifactuXmlValidationException::class);
        $this->validator()->validate($xml);
    }

    /** @test */
    public function invalid_element_ordering_fails(): void
    {
        // Swap TipoFactura and NombreRazonEmisor - the schema is a strict
        // <sequence>, so this must be rejected even though both elements
        // are individually present and individually valid.
        $xml = str_replace(
            "<NombreRazonEmisor>Empresa Ficticia SL</NombreRazonEmisor>\n   <TipoFactura>F1</TipoFactura>",
            "<TipoFactura>F1</TipoFactura>\n   <NombreRazonEmisor>Empresa Ficticia SL</NombreRazonEmisor>",
            $this->validAltaXml()
        );

        $this->assertFalse($this->validator()->isValid($xml));
    }
}
