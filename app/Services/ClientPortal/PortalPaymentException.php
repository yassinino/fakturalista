<?php

namespace App\Services\ClientPortal;

/**
 * A portal payment request that can't proceed. `reason` is a stable
 * machine code (also the translation key suffix under
 * invoice.portal_payment.*); `status` is the HTTP status to answer with.
 */
class PortalPaymentException extends \RuntimeException
{
    public function __construct(public readonly string $reason, public readonly int $status)
    {
        parent::__construct($reason);
    }
}
