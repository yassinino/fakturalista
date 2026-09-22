<?php

namespace App\Services\Verifactu\Aeat;

use App\Exceptions\Verifactu\AeatTlsException;
use App\Exceptions\Verifactu\AeatTransportException;
use App\Models\Verifactu\VerifactuSubmission;
use App\Services\Verifactu\Auth\AeatAuthenticationProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Talks to AEAT's RegFactuSistemaFacturacion SOAP operation. Contains no
 * invoice/customer business logic - only ever sees an already-built
 * VerifactuSubmission payload and the emisor's own NIF/name for the
 * envelope's Cabecera. Never called from a controller - see
 * docs/verifactu-aeat-connectivity.md §7.
 */
class AeatVerifactuClient
{
    private const NS_SUMINISTRO_LR = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroLR.xsd';
    private const NS_SUMINISTRO = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd';
    private const NS_SOAPENV = 'http://schemas.xmlsoap.org/soap/envelope/';

    public function __construct(
        private AeatEndpointResolver $endpoints,
        private AeatAuthenticationProvider $auth,
        private AeatResponseParser $parser,
    ) {
    }

    /**
     * @throws AeatTransportException|AeatTlsException|\App\Exceptions\Verifactu\AeatSoapFaultException
     * @throws \App\Exceptions\Verifactu\VerifactuCertificateException
     */
    public function submit(VerifactuSubmission $submission, string $emisorNif, string $emisorNombre): AeatSubmissionResult
    {
        // Orden HAC/1177/2024 art. 16.2 flow control is one mechanism for
        // resubmission delay; separately, AEAT's own FAQ (Preguntas
        // frecuentes, actualizadas 21/07/2026, "Sistemas VERI*FACTU")
        // states that after a connectivity incident (network/service
        // outage) a resend "deberá reintentarse periódicamente...
        // incorporando en el envío la 'S' en el campo 'Incidencia'" -
        // this submission having a prior failed attempt (retry_count > 0)
        // is exactly that case.
        $envelope = $this->buildEnvelope($submission->payload_xml, $emisorNif, $emisorNombre, $submission->retry_count > 0);
        $certificate = $this->auth->resolveCertificate($submission->nif);
        $pemPath     = $certificate->writeTemporaryPemFile();

        try {
            $response = Http::withOptions([
                    'cert'            => $pemPath,
                    'ssl_key'         => $pemPath,
                    'connect_timeout' => (int) config('verifactu.aeat.connect_timeout'),
                ])
                ->timeout((int) config('verifactu.aeat.timeout'))
                ->withHeaders(['SOAPAction' => ''])
                // withBody()'s 2nd argument sets the Content-Type header
                // itself - passing it here (rather than via withHeaders(),
                // which a later withBody() call would silently override)
                // is the only way it actually reaches the wire.
                ->withBody($envelope, 'text/xml; charset=utf-8')
                ->post($this->endpoints->endpoint());
        } catch (ConnectionException $e) {
            if ($this->looksLikeTlsFailure($e->getMessage())) {
                throw new AeatTlsException('Fallo de conexión TLS/certificado al conectar con la AEAT.', 0, $e);
            }

            throw new AeatTransportException('No se pudo conectar con el servicio web de la AEAT (entorno de pruebas).', 0, $e);
        } finally {
            @unlink($pemPath);
        }

        return $this->parser->parse($response->body());
    }

    /**
     * Best-effort classification only - Guzzle/curl don't expose a
     * dedicated TLS-failure type. See
     * docs/verifactu-aeat-connectivity.md "Known unresolved questions".
     */
    private function looksLikeTlsFailure(string $message): bool
    {
        return (bool) preg_match('/ssl|tls|certificate|cert verify|handshake/i', $message);
    }

    private function buildEnvelope(string $payloadXml, string $emisorNif, string $emisorNombre, bool $isIncidenciaRetry = false): string
    {
        $payload = new \DOMDocument();
        $payload->loadXML($payloadXml);

        $dom = new \DOMDocument('1.0', 'UTF-8');

        $envelope = $dom->createElementNS(self::NS_SOAPENV, 'soapenv:Envelope');
        $envelope->setAttributeNS(
            'http://www.w3.org/2000/xmlns/',
            'xmlns:sum',
            self::NS_SUMINISTRO_LR
        );
        $envelope->setAttributeNS(
            'http://www.w3.org/2000/xmlns/',
            'xmlns:sum1',
            self::NS_SUMINISTRO
        );
        $dom->appendChild($envelope);

        $envelope->appendChild($dom->createElementNS(self::NS_SOAPENV, 'soapenv:Header'));
        $body = $dom->createElementNS(self::NS_SOAPENV, 'soapenv:Body');
        $envelope->appendChild($body);

        $regFactu = $dom->createElementNS(self::NS_SUMINISTRO_LR, 'sum:RegFactuSistemaFacturacion');
        $body->appendChild($regFactu);

        $cabecera = $dom->createElementNS(self::NS_SUMINISTRO_LR, 'sum:Cabecera');
        $obligado = $dom->createElementNS(self::NS_SUMINISTRO, 'sum1:ObligadoEmision');
        $obligado->appendChild($dom->createElementNS(self::NS_SUMINISTRO, 'sum1:NombreRazon', $emisorNombre));
        $obligado->appendChild($dom->createElementNS(self::NS_SUMINISTRO, 'sum1:NIF', $emisorNif));
        $cabecera->appendChild($obligado);

        if ($isIncidenciaRetry) {
            $remisionVoluntaria = $dom->createElementNS(self::NS_SUMINISTRO, 'sum1:RemisionVoluntaria');
            $remisionVoluntaria->appendChild($dom->createElementNS(self::NS_SUMINISTRO, 'sum1:Incidencia', 'S'));
            $cabecera->appendChild($remisionVoluntaria);
        }

        $regFactu->appendChild($cabecera);

        $registroFactura = $dom->createElementNS(self::NS_SUMINISTRO_LR, 'sum:RegistroFactura');
        $imported         = $dom->importNode($payload->documentElement, true);
        $registroFactura->appendChild($imported);
        $regFactu->appendChild($registroFactura);

        return $dom->saveXML();
    }
}
