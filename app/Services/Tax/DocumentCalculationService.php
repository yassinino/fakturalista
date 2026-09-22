<?php

namespace App\Services\Tax;

/**
 * The single, authoritative, country-neutral financial calculator for
 * invoices and quotes - Morocco Phase 1C.1
 * (docs/morocco-phase-1c1-generic-tax-foundation.md). Both
 * InvoiceController and QuoteController (and QuoteToInvoiceService) call
 * this same class, so there is exactly one calculation formula and one
 * rounding policy in the whole application - not one per document type,
 * and not one per rendering layer (PDF included, see
 * TemplateRendererService).
 *
 * Deliberately generic: a tax rate is just a number. Nothing in this
 * class assumes, whitelists, or special-cases 4/10/21 (Spain's current
 * rates) - any non-negative rate works identically, today, without any
 * Moroccan rate ever being decided or hardcoded here.
 *
 * Uses bcmath, not native float arithmetic, for every intermediate step -
 * see the class docblock on round() for why (the audit's rounding-
 * inconsistency findings were traced, in part, to naive float rounding).
 */
class DocumentCalculationService
{
    /**
     * Canonical monetary precision. Every amount this service ever
     * returns (line-level or document-level) is rounded to exactly this
     * many decimal places, at exactly the three points described in
     * calculate()'s docblock - never anywhere else, never twice.
     */
    private const SCALE = 2;

    /**
     * Internal bcmath working precision - deliberately higher than
     * SCALE so that intermediate multiplications/divisions don't lose
     * precision before the deliberate, explicit rounding points below.
     */
    private const WORKING_SCALE = 6;

    /**
     * @param array<int, array{quantity: float|int|string, unit_price: float|int|string, discount?: float|int|string, tax_rate?: float|int|string, treatment?: string}> $lines
     *        `treatment` (Morocco Phase 1C.2, see TaxTreatment) is a pure
     *        semantic label - taxable/exempt/out_of_scope. Non-taxable
     *        treatments require a zero rate. Exempt and taxable zero
     *        lines compute the same tax but remain separate buckets.
     *        Defaults to TaxTreatment::TAXABLE so every Phase 1C.1
     *        caller that doesn't pass it keeps working unchanged.
     * @param float $headerDiscountRate a whole-document discount percentage (0-100), applied proportionally across every rate bucket - see the class docblock in DocumentCalculationResult.
     */
    public function calculate(array $lines, float $headerDiscountRate = 0.0): DocumentCalculationResult
    {
        $headerDiscountRate = (string) $headerDiscountRate;

        $lineResults      = [];
        $rawBaseByGroup   = []; // groupKey (string) => bcmath string, sum of already-rounded per-line taxable bases
        $rateByGroup      = []; // groupKey (string) => float rate, for output
        $treatmentByGroup = []; // groupKey (string) => treatment, for output

        foreach ($lines as $line) {
            $qty       = (string) ($line['quantity'] ?? 0);
            $price     = (string) ($line['unit_price'] ?? 0);
            $discount  = (string) ($line['discount'] ?? 0);
            $rate      = (string) ($line['tax_rate'] ?? 0);
            $treatment = $line['treatment'] ?? TaxTreatment::TAXABLE;
            if (!TaxTreatment::isValid($treatment)) {
                throw new \InvalidArgumentException('Unknown tax treatment.');
            }
            if ($treatment !== TaxTreatment::TAXABLE && (float) $rate != 0) {
                throw new \InvalidArgumentException('Non-taxable treatments require a zero tax rate.');
            }

            // gross -> line discount amount -> taxable base. Each step
            // rounded once, immediately, so every later sum is a sum of
            // already-2dp-exact values (no drift accumulates across lines).
            $gross           = $this->round(bcmul($qty, $price, self::WORKING_SCALE));
            $discountAmount  = $this->round($this->percentOf($gross, $discount));
            $taxableBase     = $this->round(bcsub($gross, $discountAmount, self::WORKING_SCALE));

            $lineResults[] = [
                'quantity'         => (float) $qty,
                'unit_price'       => (float) $price,
                'discount'         => (float) $discount,
                'tax_rate'         => (float) $rate,
                'treatment'        => $treatment,
                'gross_amount'     => (float) $gross,
                'discount_amount'  => (float) $discountAmount,
                'taxable_base'     => (float) $taxableBase,
            ];

            // Grouped by (rate, treatment): an exempt line and a taxable
            // line that both happen to be 0% must never be merged into
            // one indistinguishable breakdown row (Phase 1C.2 §9).
            $groupKey = $this->rateKey($rate) . '|' . $treatment;
            $rawBaseByGroup[$groupKey]   = bcadd($rawBaseByGroup[$groupKey] ?? '0', $taxableBase, self::WORKING_SCALE);
            $rateByGroup[$groupKey]      = (float) $rate;
            $treatmentByGroup[$groupKey] = $treatment;
        }

        // Subtotal (HT) shown pre-header-discount, matching the existing
        // UI convention (the header discount is its own, separately
        // displayed line, not baked into the subtotal figure).
        $subTotal = '0';
        foreach ($rawBaseByGroup as $base) {
            $subTotal = bcadd($subTotal, $base, self::WORKING_SCALE);
        }
        $subTotal = $this->round($subTotal);

        $headerDiscountAmount = $this->round($this->percentOf($subTotal, $headerDiscountRate));

        // Per-(rate,treatment) breakdown: the header discount is applied
        // proportionally to each group's own base before computing that
        // group's tax - algebraically identical to reducing the tax by
        // the same percentage afterward (the two are commutative), but
        // expressed this way so $taxBreakdown's own taxable_base is the
        // actual base tax_amount was computed from (base * rate/100 ==
        // tax_amount, exactly, for every row - no hidden header-discount
        // adjustment the reader can't see).
        $discountFactor = bcsub('1', bcdiv($headerDiscountRate, '100', self::WORKING_SCALE), self::WORKING_SCALE);

        $breakdown = [];
        $totalTax  = '0';
        foreach ($rawBaseByGroup as $groupKey => $rawBase) {
            $adjustedBase = $this->round(bcmul($rawBase, $discountFactor, self::WORKING_SCALE));
            $taxAmount    = $this->round($this->percentOf($adjustedBase, (string) $rateByGroup[$groupKey]));

            $breakdown[] = [
                'rate'         => $rateByGroup[$groupKey],
                'treatment'    => $treatmentByGroup[$groupKey],
                'taxable_base' => (float) $adjustedBase,
                'tax_amount'   => (float) $taxAmount,
            ];

            $totalTax = bcadd($totalTax, $taxAmount, self::WORKING_SCALE);
        }
        $totalTax = $this->round($totalTax);

        usort($breakdown, fn (array $a, array $b) => $a['rate'] <=> $b['rate']);

        $grandTotal = $this->round(
            bcadd(bcsub($subTotal, $headerDiscountAmount, self::WORKING_SCALE), $totalTax, self::WORKING_SCALE)
        );

        return new DocumentCalculationResult(
            lines: $lineResults,
            subTotal: (float) $subTotal,
            discountAmount: (float) $headerDiscountAmount,
            taxBreakdown: $breakdown,
            totalTax: (float) $totalTax,
            grandTotal: (float) $grandTotal,
        );
    }

    private function percentOf(string $amount, string $percent): string
    {
        return bcdiv(bcmul($amount, $percent, self::WORKING_SCALE), '100', self::WORKING_SCALE);
    }

    /**
     * A rate like 4, "4", 4.0, or "4.00" must all group into the SAME
     * bucket - grouping by the raw string would treat them as different
     * lines and silently split one rate's tax across multiple rows.
     */
    private function rateKey(string $rate): string
    {
        return number_format((float) $rate, 4, '.', '');
    }

    /**
     * Round-half-up to SCALE decimals, using bcmath throughout - never a
     * native float round(). This matters: PHP's float round() can
     * mis-round values whose exact decimal representation isn't exactly
     * representable in binary (the classic example is round(2.675, 2),
     * which yields 2.67 with native floats instead of the mathematically
     * correct 2.68) - the exact class of bug the Phase 1C audit flagged
     * as a rounding-inconsistency risk. bcmath operates on the decimal
     * string directly, so this can't happen.
     */
    private function round(string $value, int $scale = self::SCALE): string
    {
        $negative = bccomp($value, '0', 10) < 0;
        $abs      = $negative ? bcmul($value, '-1', 10) : $value;

        $factor  = bcpow('10', (string) $scale);
        $shifted = bcadd(bcmul($abs, $factor, 10), '0.5', 10);
        $rounded = bcdiv($shifted, '1', 0); // truncates the fractional part -> floor(shifted + 0.5)
        $result  = bcdiv($rounded, $factor, $scale);

        return ($negative && bccomp($result, '0', $scale) !== 0) ? "-{$result}" : $result;
    }
}
