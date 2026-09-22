<?php

namespace App\Services\Verifactu\Auth;

use App\Exceptions\Verifactu\VerifactuCertificateException;
use App\Models\Verifactu\VerifactuCertificate;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Fakturalista V1's authentication strategy: each taxpayer uses their
 * own electronic certificate (docs/verifactu-aeat-connectivity.md §2).
 * Decrypts on demand, inside the caller's tenant context, and returns a
 * value object meant to live only for the duration of one SOAP call -
 * never stores decrypted material anywhere.
 */
class CustomerCertificateProvider implements AeatAuthenticationProvider
{
    public function resolveCertificate(string $nif): AeatClientCertificate
    {
        $certificate = VerifactuCertificate::where('nif', $nif)->first();

        if (!$certificate) {
            throw new VerifactuCertificateException(
                "No hay ningún certificado VERI*FACTU configurado para el NIF {$nif}."
            );
        }

        if ($certificate->isExpired()) {
            throw new VerifactuCertificateException(
                "El certificado VERI*FACTU configurado para el NIF {$nif} ha caducado."
            );
        }

        if (!Storage::disk('local')->exists($certificate->encrypted_file_path)) {
            Log::channel('verifactu')->error('verifactu.certificate.file_missing', ['nif' => $nif]);

            throw new VerifactuCertificateException(
                "No se puede leer el certificado VERI*FACTU configurado para el NIF {$nif}."
            );
        }

        $encryptedBytes = Storage::disk('local')->get($certificate->encrypted_file_path);
        $pkcs12Bytes    = Crypt::decryptString($encryptedBytes);

        $parsed = [];
        $ok     = openssl_pkcs12_read($pkcs12Bytes, $parsed, $certificate->passphrase);

        // Never log $e/openssl_error_string() output verbatim without
        // review - in practice these are generic ("mac verify failure")
        // but we deliberately don't relay them to the caller either way.
        if (!$ok || empty($parsed['cert']) || empty($parsed['pkey'])) {
            Log::channel('verifactu')->error('verifactu.certificate.decrypt_failed', ['nif' => $nif]);

            throw new VerifactuCertificateException(
                "No se pudo leer el certificado VERI*FACTU configurado para el NIF {$nif}."
            );
        }

        return new AeatClientCertificate($parsed['cert'], $parsed['pkey']);
    }
}
