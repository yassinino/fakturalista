<?php

namespace App\Services\Verifactu\Auth;

/**
 * Decrypted certificate + private key material, held IN MEMORY ONLY for
 * the duration of a single SOAP call. Never serialized (no queue job
 * ever holds one - see docs/verifactu-aeat-connectivity.md §9), never
 * logged, never returned from any controller/API response.
 *
 * writeTemporaryPemFile() is the one place this material touches disk,
 * because PHP's SoapClient stream context requires `local_cert` to be a
 * filesystem path, not a PEM string - see docs/verifactu-aeat-connectivity.md
 * §3 for why this is an acknowledged, documented exposure window, not a
 * hidden one. The caller MUST delete the returned path when done
 * (AeatVerifactuClient does this in a finally block).
 */
class AeatClientCertificate
{
    public function __construct(
        private string $certPem,
        private string $keyPem,
    ) {
    }

    /**
     * Writes cert+key as one combined PEM to a 0600 temp file and
     * returns its path. Caller owns deleting it.
     */
    public function writeTemporaryPemFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'vfcert_');

        if ($path === false) {
            throw new \RuntimeException('No se pudo crear un archivo temporal para el certificado.');
        }

        file_put_contents($path, $this->certPem . "\n" . $this->keyPem);
        chmod($path, 0600);

        return $path;
    }
}
