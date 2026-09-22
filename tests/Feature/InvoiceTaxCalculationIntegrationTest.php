<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceTaxLine;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Pdf\TemplateRendererService;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Morocco Phase 1C.1 - docs/morocco-phase-1c1-generic-tax-foundation.md.
 *
 * Server-authoritative behavior through the real HTTP layer: the backend
 * must recompute from submitted lines, reject invalid numeric input, and
 * never recalculate a historical (issued) invoice. Covers N, O, P, S.
 */
class InvoiceTaxCalculationIntegrationTest extends TestCase
{
    private function makeTenant(): array
    {
        $id     = 'test-taxcalc-' . uniqid();
        $tenant = Tenant::create(['id' => $id]);
        $domain = $id . '.fakturalista.test';
        $tenant->domains()->create(['domain' => $domain]);

        tenancy()->initialize($tenant);
        $user = User::factory()->create();
        CompanyProfile::create([
            'legal_name'              => 'Test Co',
            'country_code'            => 'ES',
            'tax_id'                  => 'B12345678',
            'invoice_prefix'          => 'INV',
            'onboarding_completed_at' => now(),
        ]);
        $customerUuid = Customer::factory()->create()->uuid;
        tenancy()->end();

        return [$tenant, $user, $domain, $customerUuid];
    }

    private function apiUrl(string $domain, string $path): string
    {
        return 'http://' . $domain . $path;
    }

    /** @test */
    public function o_a_malicious_client_supplied_total_is_ignored_and_recomputed_server_side(): void
    {
        [$tenant, $user, $domain, $customerUuid] = $this->makeTenant();
        $this->actingAs($user, 'api');

        // Real line: 1 x 100 @ 21% = 121.00. Client lies about every total.
        $response = $this->postJson($this->apiUrl($domain, '/api/invoices'), [
            'customer_id'     => $customerUuid,
            'date'            => now()->toDateString(),
            'expiration_date' => now()->addDays(30)->toDateString(),
            'sub_total'       => 1.00,
            'vta'             => 0.01,
            'vta4'            => 0,
            'vta10'           => 0,
            'vta21'           => 0.01,
            'total'           => 1.01,
            'discount_rate'   => 0,
            'carts'           => [
                ['qty' => 1, 'price' => 100, 'discount' => 0, 'vta' => 21, 'total' => 1, 'description' => 'Line'],
            ],
        ]);

        $response->assertStatus(200);

        $tenant->run(function () {
            $invoice = Invoice::latest('id')->first();
            $this->assertEquals(100.0, (float) $invoice->sub_total, 'sub_total must be recomputed, not the submitted lie.');
            $this->assertEquals(21.0, (float) $invoice->vta21);
            $this->assertEquals(21.0, (float) $invoice->vta);
            $this->assertEquals(121.0, (float) $invoice->total, 'total must be server-calculated, never the submitted 1.01.');
        });

        $tenant->delete();
    }

    /** @test */
    public function p_negative_quantity_is_rejected(): void
    {
        [$tenant, $user, $domain, $customerUuid] = $this->makeTenant();
        $this->actingAs($user, 'api');

        $response = $this->postJson($this->apiUrl($domain, '/api/invoices'), [
            'customer_id'     => $customerUuid,
            'date'            => now()->toDateString(),
            'expiration_date' => now()->addDays(30)->toDateString(),
            'carts'           => [
                ['qty' => -5, 'price' => 100, 'discount' => 0, 'vta' => 21],
            ],
        ]);

        $response->assertStatus(422);
        $tenant->delete();
    }

    /** @test */
    public function p_negative_tax_rate_is_rejected(): void
    {
        [$tenant, $user, $domain, $customerUuid] = $this->makeTenant();
        $this->actingAs($user, 'api');

        $response = $this->postJson($this->apiUrl($domain, '/api/invoices'), [
            'customer_id'     => $customerUuid,
            'date'            => now()->toDateString(),
            'expiration_date' => now()->addDays(30)->toDateString(),
            'carts'           => [
                ['qty' => 1, 'price' => 100, 'discount' => 0, 'vta' => -21],
            ],
        ]);

        $response->assertStatus(422);
        $tenant->delete();
    }

    /** @test */
    public function p_discount_over_100_percent_is_rejected(): void
    {
        [$tenant, $user, $domain, $customerUuid] = $this->makeTenant();
        $this->actingAs($user, 'api');

        $response = $this->postJson($this->apiUrl($domain, '/api/invoices'), [
            'customer_id'     => $customerUuid,
            'date'            => now()->toDateString(),
            'expiration_date' => now()->addDays(30)->toDateString(),
            'carts'           => [
                ['qty' => 1, 'price' => 100, 'discount' => 150, 'vta' => 21],
            ],
        ]);

        $response->assertStatus(422);
        $tenant->delete();
    }

    /** @test */
    public function n_the_persisted_tax_breakdown_correctly_applies_the_line_discount_unlike_the_old_pdf_bug(): void
    {
        [$tenant, $user, $domain, $customerUuid] = $this->makeTenant();
        $this->actingAs($user, 'api');

        // 2 x 100 @ 21%, 50% line discount -> gross=200, base=100, tax=21.
        // The old PDF formula ignored the discount entirely and would
        // have shown tax on the full 200 (42), not the discounted 100 (21).
        $this->postJson($this->apiUrl($domain, '/api/invoices'), [
            'customer_id'     => $customerUuid,
            'date'            => now()->toDateString(),
            'expiration_date' => now()->addDays(30)->toDateString(),
            'carts'           => [
                ['qty' => 2, 'price' => 100, 'discount' => 50, 'vta' => 21],
            ],
        ])->assertStatus(200);

        $tenant->run(function () {
            $invoice = Invoice::latest('id')->first();
            $taxLine = InvoiceTaxLine::where('invoice_id', $invoice->id)->first();

            $this->assertNotNull($taxLine);
            $this->assertEquals(100.0, (float) $taxLine->taxable_base);
            $this->assertEquals(21.0, (float) $taxLine->tax_amount);
            $this->assertEquals(21.0, (float) $invoice->vta21, 'Stored legacy column must match the discount-adjusted breakdown.');

            // The PDF must not crash and must be driven by this same data.
            $pdf = app(TemplateRendererService::class)->render($invoice->fresh(), 'invoice');
            $this->assertStringStartsWith('%PDF', $pdf);
        });

        $tenant->delete();
    }

    /** @test */
    public function s_an_issued_historical_invoice_with_no_tax_lines_is_never_silently_recalculated(): void
    {
        [$tenant, $user, $domain, $customerUuid] = $this->makeTenant();

        // Simulate a pre-Phase-1C.1 invoice: created directly (bypassing
        // the controller/calculator entirely, exactly as any invoice
        // issued before this phase existed would have been), with no
        // invoice_tax_lines rows and financial values that would NOT
        // match what the new engine would compute from these same lines
        // (21 vs. the "wrong" 19.99 stored here on purpose).
        $invoiceUuid = $tenant->run(function () use ($customerUuid) {
            $customer = Customer::where('uuid', $customerUuid)->first();
            $invoice = Invoice::create([
                'uuid'            => Str::uuid()->toString(),
                'reference'       => 'INV-LEGACY-1',
                'customer_id'     => $customer->id,
                'date'            => now()->toDateString(),
                'expiration_date' => now()->addDays(30)->toDateString(),
                'status'          => Invoice::STATUS_ISSUED,
                'issued_at'       => now(),
                'invoice_series'  => 'INV',
                'invoice_number'  => 999001,
                'sub_total'       => 100.00,
                'vta'             => 19.99, // deliberately NOT what 21% of 100 would be (21.00)
                'vta4'            => 0, 'vta10' => 0, 'vta21' => 19.99,
                'total'           => 119.99,
                'discount_rate'   => 0, 'discount_amount' => 0,
            ]);
            \App\Models\Cart::create([
                'cartable_type' => 'App\Models\Invoice', 'cartable_id' => $invoice->id,
                'qty' => 1, 'price' => 100, 'discount' => 0, 'vta' => 21, 'total' => 100,
            ]);

            return $invoice->uuid;
        });

        $this->actingAs($user, 'api');

        // Attempting to edit a locked invoice must still be refused
        // (pre-existing behavior, unaffected by this phase) - proving
        // nothing in this phase opened a new path to touch it.
        $this->putJson($this->apiUrl($domain, '/api/invoices/' . $invoiceUuid), [
            'customer_id' => $customerUuid, 'date' => now()->toDateString(),
            'expiration_date' => now()->addDays(30)->toDateString(), 'carts' => [],
        ])->assertStatus(403);

        $tenant->run(function () use ($invoiceUuid) {
            $invoice = Invoice::where('uuid', $invoiceUuid)->first();

            // Untouched: still the deliberately "wrong" historical value,
            // not silently corrected to what the new engine would compute.
            $this->assertEquals(19.99, (float) $invoice->vta21);
            $this->assertEquals(119.99, (float) $invoice->total);
            $this->assertCount(0, $invoice->taxLines, 'A historical invoice must not gain tax-line rows it never had.');

            // Rendering must still work, falling back to the unchanged
            // cart-based computation (no invoice_tax_lines exist).
            $pdf = app(TemplateRendererService::class)->render($invoice->fresh(), 'invoice');
            $this->assertStringStartsWith('%PDF', $pdf);
        });

        $tenant->delete();
    }
}
