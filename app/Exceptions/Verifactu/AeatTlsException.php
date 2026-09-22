<?php

namespace App\Exceptions\Verifactu;

/**
 * TLS handshake / client-certificate failure. Best-effort classification
 * - PHP's SoapClient surfaces this as a generic failure, not a dedicated
 * type; see docs/verifactu-aeat-connectivity.md §7/"Known unresolved
 * questions". Never carries certificate/key material in its message.
 */
class AeatTlsException extends \RuntimeException
{
}
