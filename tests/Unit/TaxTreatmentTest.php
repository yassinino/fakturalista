<?php

namespace Tests\Unit;

use App\Services\Tax\DocumentCalculationService;
use PHPUnit\Framework\TestCase;

class TaxTreatmentTest extends TestCase
{
    public function test_zero_rate_treatments_are_distinct_without_country_configuration(): void
    {
        $result = (new DocumentCalculationService)->calculate(array_map(fn ($treatment) => [
            'quantity' => 1, 'unit_price' => 5000, 'tax_rate' => 0, 'treatment' => $treatment,
        ], ['taxable', 'exempt', 'out_of_scope']));
        $this->assertCount(3, $result->taxBreakdown);
        $this->assertSame(['taxable', 'exempt', 'out_of_scope'], array_column($result->taxBreakdown, 'treatment'));
        $this->assertEquals(0, $result->totalTax);
        $this->assertEquals(15000, $result->grandTotal);
    }

    public static function invalidTreatments(): array
    {
        return [['exempt', 20], ['out_of_scope', 10], ['unknown', 0]];
    }

    /** @dataProvider invalidTreatments */
    public function test_invalid_treatment_combinations_are_rejected_by_the_calculator($treatment, $rate): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new DocumentCalculationService)->calculate([
            ['quantity' => 1, 'unit_price' => 5000, 'tax_rate' => $rate, 'treatment' => $treatment],
        ]);
    }
}
