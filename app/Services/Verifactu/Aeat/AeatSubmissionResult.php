<?php

namespace App\Services\Verifactu\Aeat;

/**
 * A structurally valid AEAT response to RegFactuSistemaFacturacion,
 * parsed into AEAT's own terminology (RespuestaSuministro.xsd lista
 * L18/L19) - never collapsed to a bare boolean. See
 * docs/verifactu-aeat-connectivity.md §8.
 */
class AeatSubmissionResult
{
    /**
     * @param string $estadoEnvio Correcto|ParcialmenteCorrecto|Incorrecto
     * @param string|null $estadoRegistro Correcto|AceptadoConErrores|Incorrecto (this submission's own line)
     */
    public function __construct(
        public readonly string $estadoEnvio,
        public readonly ?string $estadoRegistro,
        public readonly ?string $csv,
        public readonly ?int $errorCode,
        public readonly ?string $errorDescription,
        public readonly ?string $duplicateOfIdPeticion,
        public readonly string $rawResponseXml,
        // Orden HAC/1177/2024 art. 16.2 - seconds to wait before the
        // NEXT submission for this NIF. Null when absent from the
        // response (treated as "keep the previous value" by the caller).
        public readonly ?int $tiempoEsperaEnvio = null,
    ) {
    }

    /**
     * Maps AEAT's own EstadoRegistro to Fakturalista's
     * verifactu_submissions.status bucket - see the connectivity doc's
     * §8 table.
     */
    public function mappedStatus(): string
    {
        return match ($this->estadoRegistro) {
            'Correcto'           => \App\Models\Verifactu\VerifactuSubmission::STATUS_ACCEPTED,
            'AceptadoConErrores' => \App\Models\Verifactu\VerifactuSubmission::STATUS_ACCEPTED_WITH_ERRORS,
            'Incorrecto'         => \App\Models\Verifactu\VerifactuSubmission::STATUS_REJECTED,
            default              => \App\Models\Verifactu\VerifactuSubmission::STATUS_REJECTED,
        };
    }

    public function isDuplicate(): bool
    {
        return $this->duplicateOfIdPeticion !== null;
    }
}
