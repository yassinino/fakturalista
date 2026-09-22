<?php

namespace App\Services\Tax;

/**
 * Output of DocumentCalculationService::calculate() - Morocco Phase 1C.1
 * (docs/morocco-phase-1c1-generic-tax-foundation.md). Deliberately
 * country-neutral: nothing here is named after a specific tax/country
 * (no "iva21"/"tva20"/"morocco_tax"), so it works identically for a
 * Spanish 4/10/21% invoice, a future Moroccan-rate invoice, or anything
 * else the generic engine is ever asked to compute.
 */
class DocumentCalculationResult
{
    /**
     * @param array<int, array{quantity: float, unit_price: float, discount: float, tax_rate: float, treatment: string, gross_amount: float, discount_amount: float, taxable_base: float}> $lines
     * @param array<int, array{rate: float, treatment: string, taxable_base: float, tax_amount: float}> $taxBreakdown one row per distinct (rate, treatment) combination actually used, sorted ascending by rate - Morocco Phase 1C.2: an exempt line and an ordinary taxable line at the same rate are never merged into one row (see TaxTreatment).
     */
    public function __construct(
        public readonly array $lines,
        public readonly float $subTotal,
        public readonly float $discountAmount,
        public readonly array $taxBreakdown,
        public readonly float $totalTax,
        public readonly float $grandTotal,
    ) {
    }

    /**
     * Compatibility bridge to the legacy Spanish invoices.vta4/vta10/vta21
     * columns (Morocco Phase 1A/1B/1C precedent: never touch what already
     * works for Spain). A rate with no line on this document simply maps
     * to 0.0, exactly as today. Rates outside {4,10,21} are not lost -
     * they remain fully represented in $taxBreakdown - they just have no
     * legacy column to bridge into, same as before this phase existed.
     *
     * @return array{vta4: float, vta10: float, vta21: float}
     */
    public function legacySpanishRateAmounts(): array
    {
        $byRate = [];
        foreach ($this->taxBreakdown as $row) {
            $byRate[(int) round($row['rate'])] = $row['tax_amount'];
        }

        return [
            'vta4'  => $byRate[4] ?? 0.0,
            'vta10' => $byRate[10] ?? 0.0,
            'vta21' => $byRate[21] ?? 0.0,
        ];
    }
}
