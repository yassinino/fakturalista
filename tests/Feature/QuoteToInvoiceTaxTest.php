<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\InvoiceTaxLine;
use App\Models\Quote;
use App\Models\Tenant;
use App\Services\QuoteToInvoiceService;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Morocco Phase 1C.1 §4 - docs/morocco-phase-1c1-generic-tax-foundation.md.
 *
 * The Phase 1C audit's flagged bug: a Cart line whose rate isn't exactly
 * 4/10/21 was silently dropped from the tax breakdown during quote ->
 * invoice conversion. Covers L, M.
 */
class QuoteToInvoiceTaxTest extends TestCase
{
    private function makeTenant(): Tenant
    {
        $id     = 'test-q2i-' . uniqid();
        $tenant = Tenant::create(['id' => $id]);
        $tenant->domains()->create(['domain' => $id . '.fakturalista.test']);

        return $tenant;
    }

    private function makeQuote(array $cartLines): Quote
    {
        $customer = Customer::factory()->create();

        $quote = Quote::create([
            'uuid'            => Str::uuid()->toString(),
            'reference'       => 'QUO-TEST-1',
            'customer_id'     => $customer->id,
            'date'            => now()->toDateString(),
            'status'          => Quote::STATUS_DRAFT,
            'expiration_date' => now()->addDays(30)->toDateString(),
            'sub_total'       => 0, 'discount_rate' => 0, 'discount_amount' => 0, 'vta' => 0, 'total' => 0,
        ]);

        foreach ($cartLines as $line) {
            Cart::create(array_merge([
                'cartable_type' => 'App\Models\Quote',
                'cartable_id'   => $quote->id,
                'discount'      => 0,
            ], $line));
        }

        return $quote;
    }

    /** @test */
    public function l_conversion_preserves_an_arbitrary_20_percent_rate_instead_of_dropping_it(): void
    {
        $tenant = $this->makeTenant();

        $tenant->run(function () {
            CompanyProfile::create(['legal_name' => 'Test Co', 'invoice_prefix' => 'INV']);

            $quote = $this->makeQuote([
                ['qty' => 1, 'price' => 1000, 'vta' => 20], // future Moroccan-style rate
            ]);

            $invoice = app(QuoteToInvoiceService::class)->convert($quote);

            $taxLine = InvoiceTaxLine::where('invoice_id', $invoice->id)->first();
            $this->assertNotNull($taxLine, 'The 20% line must not silently disappear from the tax breakdown.');
            $this->assertEquals(20.0, (float) $taxLine->rate);
            $this->assertEquals(1000.0, (float) $taxLine->taxable_base);
            $this->assertEquals(200.0, (float) $taxLine->tax_amount);

            // No Spanish legacy column exists for 20% - it correctly
            // stays at 0, but the tax is NOT lost (it's in invoice_tax_lines
            // and in the aggregate vta below).
            $this->assertEquals(0.0, (float) $invoice->vta4);
            $this->assertEquals(0.0, (float) $invoice->vta10);
            $this->assertEquals(0.0, (float) $invoice->vta21);
            $this->assertEquals(200.0, (float) $invoice->vta, 'The aggregate vta must still include the 20% line\'s tax.');
        });

        $tenant->delete();
    }

    /** @test */
    public function m_conversion_preserves_totals_for_a_mixed_rate_quote(): void
    {
        $tenant = $this->makeTenant();

        $tenant->run(function () {
            CompanyProfile::create(['legal_name' => 'Test Co', 'invoice_prefix' => 'INV']);

            $quote = $this->makeQuote([
                ['qty' => 1, 'price' => 100, 'vta' => 21],
                ['qty' => 1, 'price' => 100, 'vta' => 10],
                ['qty' => 1, 'price' => 100, 'vta' => 20],
            ]);

            $invoice = app(QuoteToInvoiceService::class)->convert($quote);

            $this->assertEquals(300.0, (float) $invoice->sub_total);
            $this->assertEquals(21 + 10 + 20, (float) $invoice->vta);
            $this->assertEquals(300 + 21 + 10 + 20, (float) $invoice->total);
            $this->assertCount(3, $invoice->taxLines);

            $quote->refresh();
            $this->assertEquals(Quote::STATUS_CONVERTED, $quote->status);
            $this->assertEquals($invoice->id, $quote->invoice_id);
        });

        $tenant->delete();
    }
}
