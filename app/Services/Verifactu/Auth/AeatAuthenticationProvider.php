<?php

namespace App\Services\Verifactu\Auth;

use App\Exceptions\Verifactu\VerifactuCertificateException;

/**
 * Abstraction over "how does Fakturalista authenticate to AEAT for this
 * NIF". Fakturalista V1's only strategy is CustomerCertificateProvider
 * (each taxpayer's own certificate), but AeatVerifactuClient depends on
 * this interface, not on that implementation, so a second strategy (e.g.
 * a Fakturalista-held "colaborador social" certificate) could be added
 * later without changing the SOAP client. See
 * docs/verifactu-aeat-connectivity.md §2.
 */
interface AeatAuthenticationProvider
{
    /**
     * @throws VerifactuCertificateException if no usable certificate
     *         exists for this NIF (missing, expired, or unreadable).
     */
    public function resolveCertificate(string $nif): AeatClientCertificate;
}
