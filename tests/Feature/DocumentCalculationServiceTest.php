<?php

namespace Tests\Feature;

use App\Services\Tax\DocumentCalculationService;
use Tests\TestCase;

/**
 * Morocco Phase 1C.1 - docs/morocco-phase-1c1-generic-tax-foundation.md.
 *
 * Unit-level tests of the single, authoritative, country-neutral
 * calculation engine. No tenant/HTTP fixtures needed - this is pure
 * arithmetic. Covers items A-K of the Phase 1C.1 test checklist.
 */
class DocumentCalculationServiceTest extends TestCase
{
    private function calc(): DocumentCalculationService
    {
        return app(DocumentCalculationService::class);
    }

    /** @test */
    public function a_a_single_21_percent_line_computes_correctly(): void
    {
        $result = $this->calc()->calculate([
            ['quantity' => 1, 'unit_price' => 100, 'discount' => 0, 'tax_rate' => 21],
        ]);

        $this->assertEquals(100.0, $result->subTotal);
        $this->assertEquals(21.0, $result->totalTax);
        $this->assertEquals(121.0, $result->grandTotal);
        $this->assertEquals([['rate' => 21.0, 'treatment' => 'taxable', 'taxable_base' => 100.0, 'tax_amount' => 21.0]], $result->taxBreakdown);
    }

    /** @test */
    public function b_a_single_10_percent_line_computes_correctly(): void
    {
        $result = $this->calc()->calculate([
            ['quantity' => 1, 'unit_price' => 200, 'discount' => 0, 'tax_rate' => 10],
        ]);

        $this->assertEquals(200.0, $result->subTotal);
        $this->assertEquals(20.0, $result->totalTax);
        $this->assertEquals(220.0, $result->grandTotal);
    }

    /** @test */
    public function c_a_single_4_percent_line_computes_correctly(): void
    {
        $result = $this->calc()->calculate([
            ['quantity' => 1, 'unit_price' => 50, 'discount' => 0, 'tax_rate' => 4],
        ]);

        $this->assertEquals(50.0, $result->subTotal);
        $this->assertEquals(2.0, $result->totalTax);
        $this->assertEquals(52.0, $result->grandTotal);
    }

    /** @test */
    public function d_multiple_spanish_rates_on_one_document_are_all_represented(): void
    {
        $result = $this->calc()->calculate([
            ['quantity' => 1, 'unit_price' => 100, 'discount' => 0, 'tax_rate' => 21],
            ['quantity' => 1, 'unit_price' => 100, 'discount' => 0, 'tax_rate' => 10],
            ['quantity' => 1, 'unit_price' => 100, 'discount' => 0, 'tax_rate' => 4],
        ]);

        $rates = array_column($result->taxBreakdown, 'rate');
        sort($rates);
        $this->assertEquals([4.0, 10.0, 21.0], $rates);
        $this->assertEquals(300.0, $result->subTotal);
        $this->assertEquals(21 + 10 + 4, $result->totalTax);
        $legacy = $result->legacySpanishRateAmounts();
        $this->assertEquals(4.0, $legacy['vta4']);
        $this->assertEquals(10.0, $legacy['vta10']);
        $this->assertEquals(21.0, $legacy['vta21']);
    }

    /** @test */
    public function e_an_arbitrary_20_percent_rate_works_with_no_whitelist(): void
    {
        $result = $this->calc()->calculate([
            ['quantity' => 1, 'unit_price' => 1000, 'discount' => 0, 'tax_rate' => 20],
        ]);

        $this->assertEquals(1000.0, $result->subTotal);
        $this->assertEquals(200.0, $result->totalTax);
        $this->assertEquals(1200.0, $result->grandTotal);
        $this->assertEquals([['rate' => 20.0, 'treatment' => 'taxable', 'taxable_base' => 1000.0, 'tax_amount' => 200.0]], $result->taxBreakdown);
        // A 20% rate has no Spanish legacy column to bridge into - it must
        // not be silently coerced into one of vta4/vta10/vta21 either.
        $legacy = $result->legacySpanishRateAmounts();
        $this->assertEquals(0.0, $legacy['vta4']);
        $this->assertEquals(0.0, $legacy['vta10']);
        $this->assertEquals(0.0, $legacy['vta21']);
    }

    /** @test */
    public function f_a_zero_percent_line_works_and_contributes_no_tax(): void
    {
        $result = $this->calc()->calculate([
            ['quantity' => 1, 'unit_price' => 500, 'discount' => 0, 'tax_rate' => 0],
        ]);

        $this->assertEquals(500.0, $result->subTotal);
        $this->assertEquals(0.0, $result->totalTax);
        $this->assertEquals(500.0, $result->grandTotal);
    }

    /** @test */
    public function g_line_and_header_discounts_correctly_reduce_the_taxable_base(): void
    {
        // Line: 100 * 1, 10% line discount -> base 90.
        $result = $this->calc()->calculate([
            ['quantity' => 1, 'unit_price' => 100, 'discount' => 10, 'tax_rate' => 21],
        ], headerDiscountRate: 0.0);

        $this->assertEquals(90.0, $result->lines[0]['taxable_base']);
        $this->assertEquals(10.0, $result->lines[0]['discount_amount']);
        $this->assertEquals(90.0, $result->subTotal);
        $this->assertEquals(18.9, $result->totalTax); // 90 * 21%
        $this->assertEquals(0.0, $result->discountAmount); // no header discount here

        // Now add a 10% header discount on top: base for tax purposes
        // becomes 90 * 0.9 = 81, tax = 81 * 21% = 17.01.
        $withHeader = $this->calc()->calculate([
            ['quantity' => 1, 'unit_price' => 100, 'discount' => 10, 'tax_rate' => 21],
        ], headerDiscountRate: 10.0);

        $this->assertEquals(90.0, $withHeader->subTotal); // subtotal shown pre-header-discount
        $this->assertEquals(9.0, $withHeader->discountAmount); // 90 * 10%
        $this->assertEquals(17.01, $withHeader->totalTax);
        $this->assertEquals(90.0 - 9.0 + 17.01, $withHeader->grandTotal);
    }

    /** @test */
    public function h_quantity_greater_than_one_multiplies_correctly(): void
    {
        $result = $this->calc()->calculate([
            ['quantity' => 5, 'unit_price' => 20, 'discount' => 0, 'tax_rate' => 21],
        ]);

        $this->assertEquals(100.0, $result->subTotal);
        $this->assertEquals(21.0, $result->totalTax);
    }

    /** @test */
    public function i_decimal_unit_prices_are_handled_precisely(): void
    {
        $result = $this->calc()->calculate([
            ['quantity' => 3, 'unit_price' => 33.33, 'discount' => 0, 'tax_rate' => 21],
        ]);

        // 3 * 33.33 = 99.99 exactly - not 99.98999999999999 as naive
        // float multiplication can produce.
        $this->assertEquals(99.99, $result->subTotal);
        $this->assertEquals(21.0, $result->taxBreakdown[0]['rate']);
    }

    /** @test */
    public function j_rounding_edge_cases_use_round_half_up_not_naive_float_rounding(): void
    {
        // 2.675 is the textbook case where PHP's native round(2.675, 2)
        // gives 2.67 (binary floating-point can't represent 2.675
        // exactly) instead of the mathematically correct 2.68. A line
        // priced to land exactly on a rounding boundary must round
        // correctly.
        $result = $this->calc()->calculate([
            ['quantity' => 1, 'unit_price' => 2.675, 'discount' => 0, 'tax_rate' => 0],
        ]);
        $this->assertEquals(2.68, $result->subTotal);

        // A tax amount landing exactly on .xx5 must also round up.
        $result2 = $this->calc()->calculate([
            ['quantity' => 1, 'unit_price' => 12.5, 'discount' => 0, 'tax_rate' => 4], // 12.5*4%=0.5 exactly
        ]);
        $this->assertEquals(0.5, $result2->totalTax);
    }

    /** @test */
    public function k_multiple_lines_at_the_same_rate_are_grouped_into_one_breakdown_row(): void
    {
        $result = $this->calc()->calculate([
            ['quantity' => 1, 'unit_price' => 100, 'discount' => 0, 'tax_rate' => 21],
            ['quantity' => 1, 'unit_price' => 50, 'discount' => 0, 'tax_rate' => 21],
            ['quantity' => 1, 'unit_price' => 25, 'discount' => 0, 'tax_rate' => 21],
        ]);

        $this->assertCount(1, $result->taxBreakdown);
        $this->assertEquals(175.0, $result->taxBreakdown[0]['taxable_base']);
        $this->assertEquals(36.75, $result->taxBreakdown[0]['tax_amount']);
    }
}
