<?php

namespace App\Services\EInvoicing\Exceptions;

/**
 * Thrown by EInvoiceProfileRegistry::get() when the requested profile id
 * (or the configured default, if none was given) isn't a registered,
 * working profile. The registry never silently falls back to another
 * profile - a bad id or a misconfigured config('einvoicing.profiles')
 * entry always surfaces as this one exception, never a generic
 * TypeError/fatal deep inside whatever called it.
 */
class UnknownEInvoiceProfileException extends \RuntimeException
{
    public function __construct(public readonly ?string $profileId)
    {
        parent::__construct(
            $profileId !== null && $profileId !== ''
                ? "Unknown or misconfigured e-invoicing profile \"{$profileId}\"."
                : 'No e-invoicing profile id was given and none is configured as the default.'
        );
    }
}
