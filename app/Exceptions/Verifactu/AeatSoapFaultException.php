<?php

namespace App\Exceptions\Verifactu;

/**
 * A real SOAP <Fault> - the request reached AEAT but was malformed at
 * the envelope/schema level rather than being a normal business
 * rejection (which comes back as a structurally valid
 * RespuestaRegFactuSistemaFacturacion instead - see AeatResponseParser).
 * A fault here after local XSD validation already passed is itself a
 * signal worth investigating (per S5 §9.3 in
 * docs/verifactu-implementation-plan.md).
 */
class AeatSoapFaultException extends \RuntimeException
{
    public function __construct(string $faultCode, string $faultString)
    {
        parent::__construct("SOAP Fault [{$faultCode}]: {$faultString}");
    }
}
