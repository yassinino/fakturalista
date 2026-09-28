<?php

namespace App\Services\EInvoicing;

use App\Services\EInvoicing\Contracts\EInvoiceProfileInterface;
use App\Services\EInvoicing\Exceptions\UnknownEInvoiceProfileException;

/**
 * Resolves a registered e-invoicing profile by its config('einvoicing.profiles')
 * key - the one seam a caller (InvoiceController today) goes through
 * instead of directly instantiating UblInvoiceBuilder/UblValidator or any
 * other profile's internals. Adding a future profile (e.g. Morocco DGI,
 * once that spec exists) means registering it in config/einvoicing.php,
 * not changing this class or its callers.
 */
class EInvoiceProfileRegistry
{
    /**
     * @param string|null $profileId defaults to config('einvoicing.default_profile') when omitted.
     *
     * @throws UnknownEInvoiceProfileException when $profileId (or the
     *         configured default) isn't a registered, working profile -
     *         this never silently falls back to a different profile.
     */
    public function get(?string $profileId = null): EInvoiceProfileInterface
    {
        $profileId ??= config('einvoicing.default_profile');

        if (!is_string($profileId) || $profileId === '') {
            throw new UnknownEInvoiceProfileException($profileId);
        }

        $profiles = config('einvoicing.profiles', []);
        $class    = $profiles[$profileId] ?? null;

        if (!is_string($class) || !class_exists($class)) {
            throw new UnknownEInvoiceProfileException($profileId);
        }

        $profile = app($class);

        if (!$profile instanceof EInvoiceProfileInterface) {
            throw new UnknownEInvoiceProfileException($profileId);
        }

        return $profile;
    }
}
