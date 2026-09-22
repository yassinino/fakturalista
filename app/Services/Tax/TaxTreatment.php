<?php

namespace App\Services\Tax;

/**
 * Morocco Phase 1C.2 (docs/morocco-phase-1c2-tax-configuration.md §1/§9).
 *
 * A numeric 0% rate and a legally exempt operation are not the same
 * thing - this is the generic (never Morocco-specific) semantic label
 * that keeps them distinguishable everywhere a rate is stored, without
 * DocumentCalculationService's arithmetic ever needing to branch on it
 * (an exempt line and a taxable-0% line compute identically: 0 tax).
 *
 * OUT_OF_SCOPE is defined now, unused by any preset yet, precisely so
 * that adding it later needs no schema/architecture change - only a new
 * TaxPreset entry.
 */
final class TaxTreatment
{
    public const TAXABLE     = 'taxable';
    public const EXEMPT      = 'exempt';
    public const OUT_OF_SCOPE = 'out_of_scope';

    public const ALL = [self::TAXABLE, self::EXEMPT, self::OUT_OF_SCOPE];

    public static function isValid(string $treatment): bool
    {
        return in_array($treatment, self::ALL, true);
    }
}
