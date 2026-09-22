<?php

namespace App\Exceptions\Verifactu;

/**
 * A certificate could not be read, parsed, or is missing/expired for the
 * requested NIF. Messages are always safe to display (ES/EN/FR
 * user-facing text) - never includes certificate/key content.
 */
class VerifactuCertificateException extends \RuntimeException
{
}
