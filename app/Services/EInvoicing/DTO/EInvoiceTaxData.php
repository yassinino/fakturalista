<?php

namespace App\Services\EInvoicing\DTO;

/**
 * One row of the document-level tax breakdown - a direct, 1:1 mirror of
 * App\Models\InvoiceTaxLine (already the generic, country-neutral tax
 * breakdown introduced in Morocco Phase 1C.1). Grouped by (rate,
 * treatment), exactly like the source table: an exempt 0% group and a
 * taxable 0% group are never merged.
 */
final class EInvoiceTaxData
{
    public function __construct(
        public readonly float $rate,
        // App\Services\Tax\TaxTreatment::TAXABLE|EXEMPT|OUT_OF_SCOPE
        public readonly string $treatment,
        public readonly float $taxableBase,
        public readonly float $taxAmount,
    ) {
    }

    public function toArray(): array
    {
        return [
            'rate'         => $this->rate,
            'treatment'    => $this->treatment,
            'taxable_base' => $this->taxableBase,
            'tax_amount'   => $this->taxAmount,
        ];
    }
}
