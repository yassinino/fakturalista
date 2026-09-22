<?php

namespace App\Services\Verifactu\Aeat;

/**
 * The ONLY place an AEAT SOAP endpoint URL is allowed to come from.
 * Phase 2D supports exactly one environment: 'test'. The production
 * endpoint string does not exist anywhere in this codebase (see
 * docs/verifactu-aeat-connectivity.md §1), so there is no
 * misconfiguration that can make this resolve to production - an
 * unrecognized value throws instead of falling back to anything.
 */
class AeatEndpointResolver
{
    public function endpoint(): string
    {
        $environment = config('verifactu.aeat.environment');

        if ($environment !== 'test') {
            throw new \RuntimeException(
                "Entorno AEAT no soportado en esta fase: '{$environment}'. Solo 'test' está permitido "
                . '(VERIFACTU_AEAT_ENV). La conectividad de producción no está implementada.'
            );
        }

        $endpoint = config('verifactu.aeat.endpoints.test');

        if (empty($endpoint)) {
            throw new \RuntimeException('No hay configurado un endpoint de AEAT para el entorno de pruebas.');
        }

        return $endpoint;
    }

    public function environment(): string
    {
        return config('verifactu.aeat.environment');
    }
}
