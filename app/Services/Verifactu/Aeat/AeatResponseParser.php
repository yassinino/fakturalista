<?php

namespace App\Services\Verifactu\Aeat;

use App\Exceptions\Verifactu\AeatSoapFaultException;

/**
 * Parses the raw AEAT response XML (RespuestaRegFactuSistemaFacturacion,
 * RespuestaSuministro.xsd) via DOM+XPath - deliberately not relying on
 * SoapClient's automatic stdClass mapping, whose shape can vary
 * depending on whether repeatable elements happen to repeat. Fakturalista
 * only ever submits one record per envelope (see
 * docs/verifactu-aeat-connectivity.md §11), so only the first
 * RespuestaLinea is read.
 */
class AeatResponseParser
{
    private const NS_RESPUESTA = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/RespuestaSuministro.xsd';
    private const NS_SUMINISTRO = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd';
    private const NS_SOAPENV = 'http://schemas.xmlsoap.org/soap/envelope/';

    /**
     * @throws AeatSoapFaultException if the body is a SOAP Fault rather
     *         than a structurally valid response.
     */
    public function parse(string $rawResponseXml): AeatSubmissionResult
    {
        $previousSetting = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $dom    = new \DOMDocument();
            $loaded = $dom->loadXML($rawResponseXml);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousSetting);
        }

        if (!$loaded) {
            return new AeatSubmissionResult(
                estadoEnvio: 'Incorrecto',
                estadoRegistro: null,
                csv: null,
                errorCode: null,
                errorDescription: null,
                duplicateOfIdPeticion: null,
                rawResponseXml: $rawResponseXml,
            );
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('soapenv', self::NS_SOAPENV);
        $xpath->registerNamespace('sfR', self::NS_RESPUESTA);
        $xpath->registerNamespace('sf', self::NS_SUMINISTRO);

        $fault = $xpath->query('//soapenv:Fault')->item(0);
        if ($fault) {
            $code   = $xpath->query('.//faultcode', $fault)->item(0)?->textContent ?? 'unknown';
            $string = $xpath->query('.//faultstring', $fault)->item(0)?->textContent ?? 'unknown';

            throw new AeatSoapFaultException($code, $string);
        }

        $estadoEnvio    = $this->text($xpath, '//sfR:EstadoEnvio');
        $csv            = $this->text($xpath, '//sfR:CSV');
        // TiempoEsperaEnvio: schema analysis (RespuestaBaseType lives in
        // RespuestaSuministro.xsd with elementFormDefault="qualified")
        // says this belongs to the sfR namespace, but AEAT's own doc
        // example shows it prefixed "sf:" - genuinely ambiguous between
        // two primary-source signals with no real sample response to
        // settle it (see docs/verifactu-aeat-connectivity.md). Checked
        // defensively in both namespaces rather than picking one.
        $tiempoEsperaText = $this->text($xpath, '//sfR:TiempoEsperaEnvio')
            ?? $this->text($xpath, '//sf:TiempoEsperaEnvio');
        $tiempoEspera   = $tiempoEsperaText !== null ? (int) $tiempoEsperaText : null;

        $line = $xpath->query('//sfR:RespuestaLinea')->item(0);

        $estadoRegistro   = null;
        $errorCode        = null;
        $errorDescription = null;
        $duplicateOf      = null;

        if ($line) {
            $estadoRegistro   = $this->text($xpath, './/sfR:EstadoRegistro', $line);
            $errorCodeText    = $this->text($xpath, './/sfR:CodigoErrorRegistro', $line);
            $errorCode        = $errorCodeText !== null ? (int) $errorCodeText : null;
            $errorDescription = $this->text($xpath, './/sfR:DescripcionErrorRegistro', $line);
            $duplicateOf      = $this->text($xpath, './/sf:RegistroDuplicado/sf:IdPeticionRegistroDuplicado', $line);
        }

        return new AeatSubmissionResult(
            estadoEnvio: $estadoEnvio ?? 'Incorrecto',
            estadoRegistro: $estadoRegistro,
            csv: $csv,
            errorCode: $errorCode,
            errorDescription: $errorDescription,
            duplicateOfIdPeticion: $duplicateOf,
            rawResponseXml: $rawResponseXml,
            tiempoEsperaEnvio: $tiempoEspera,
        );
    }

    private function text(\DOMXPath $xpath, string $query, ?\DOMNode $context = null): ?string
    {
        $node = $context ? $xpath->query($query, $context)->item(0) : $xpath->query($query)->item(0);

        return $node?->textContent;
    }
}
