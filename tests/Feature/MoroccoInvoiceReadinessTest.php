<?php

namespace Tests\Feature;

use App\Http\Controllers\InvoiceController;
use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Pdf\TemplateRendererService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * Morocco Phase 1C.3 - docs/morocco-phase-1c3-invoice-readiness.md.
 *
 * Structural invoice/quote readiness for Moroccan tenants: the bank/
 * payment info block, the per-rate taxable-base line in the Moroccan tax
 * summary, the rectification-path generic-tax-breakdown fix, Spain/
 * VERI*FACTU isolation on the PDF path, and Moroccan quote rendering.
 * Does not re-test what Phases 1B/1C.1/1C.2 already cover (seller/customer
 * identity snapshots, tax calculation correctness, VERI*FACTU XML) - see
 * MoroccoIdentityTest, InvoiceIdentitySnapshotTest, CountryTaxConfigurationTest,
 * DocumentCalculationServiceTest and the VerifactuXmlBuilderTest suite.
 */
class MoroccoInvoiceReadinessTest extends TestCase
{
    private Tenant $tenant;
    private string $domain;
    private string $customerUuid;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create(['id' => 'test-1c3-' . uniqid()]);
        $this->domain = $this->tenant->id . '.fakturalista.test';
        $this->tenant->domains()->create(['domain' => $this->domain]);
        $user = $this->tenant->run(function () {
            CompanyProfile::create([
                'legal_name' => 'Phase 1C.3 Test SARL', 'country_code' => 'MA',
                'currency' => 'MAD', 'locale' => 'fr', 'invoice_prefix' => 'INV',
                'ice' => '001234567000099', 'onboarding_completed_at' => now(),
            ]);
            $this->customerUuid = Customer::factory()->create(['ice' => '009876543000011'])->uuid;
            return User::factory()->create();
        });
        $this->actingAs($user, 'api');
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        $this->tenant->delete();
        parent::tearDown();
    }

    private function url(string $path): string
    {
        return 'http://' . $this->domain . '/api/' . $path;
    }

    private function payload(array $lines): array
    {
        return [
            'customer_id' => $this->customerUuid, 'date' => '2026-09-21',
            'expiration_date' => '2026-10-21', 'status' => 'draft', 'discount_rate' => 0,
            // Deliberately untrustworthy client totals must be ignored (Phase 1C.1).
            'sub_total' => 1, 'total' => 1, 'vta' => 1, 'vta21' => 999,
            'carts' => array_map(fn ($line) => $line + [
                'qty' => 1, 'discount' => 0, 'total' => 1, 'description' => 'Web development',
            ], $lines),
        ];
    }

    private function invoice(array $lines): Invoice
    {
        $this->postJson($this->url('invoices'), $this->payload($lines))->assertOk();
        return $this->tenant->run(fn () => Invoice::latest('id')->first());
    }

    private function issuedInvoice(array $lines): Invoice
    {
        $invoice = $this->invoice($lines);
        $this->postJson($this->url('invoices/' . $invoice->uuid . '/issue'))->assertOk();
        return $this->tenant->run(fn () => $invoice->fresh());
    }

    private function pdfData(object $document, string $type = 'invoice'): array
    {
        $data = [];
        View::composer('pdf.document', function ($view) use (&$data) { $data = $view->getData(); });
        $pdf = app(TemplateRendererService::class)->render($document, $type);
        $this->assertStringStartsWith('%PDF', $pdf);
        return $data;
    }

    // ── Bank / payment info (§11) ─────────────────────────────────

    /** @test */
    public function bank_details_render_on_the_invoice_pdf_when_configured(): void
    {
        $this->tenant->run(fn () => CompanyProfile::first()->update([
            'bank_name' => 'Attijariwafa Bank',
            'iban'      => 'MA64 2300 1029 0660 5211 0184 0061',
            'swift'     => 'BCMAMAMC',
        ]));
        $invoice = $this->invoice([['price' => 5000, 'vta' => 20]]);

        $this->tenant->run(function () use ($invoice) {
            $data = $this->pdfData($invoice);
            $html = view('pdf.components._footer', $data)->render();
            $this->assertStringContainsString('Attijariwafa Bank', $html);
            $this->assertStringContainsString('MA64 2300 1029 0660 5211 0184 0061', $html);
            $this->assertStringContainsString('BCMAMAMC', $html);
        });
    }

    /** @test */
    public function bank_details_section_is_absent_when_not_configured(): void
    {
        $invoice = $this->invoice([['price' => 5000, 'vta' => 20]]);

        $this->tenant->run(function () use ($invoice) {
            $data = $this->pdfData($invoice);
            $html = view('pdf.components._footer', $data)->render();
            $this->assertStringNotContainsString('IBAN', $html);
            $this->assertStringNotContainsString('SWIFT', $html);
            $this->assertStringNotContainsString('Coordonnées bancaires', $html);
        });
    }

    // ── Moroccan tax summary base line (§6) ───────────────────────

    /** @test */
    public function moroccan_tax_summary_shows_the_taxable_base_per_rate(): void
    {
        $invoice = $this->invoice([['price' => 5000, 'vta' => 20], ['price' => 2000, 'vta' => 10]]);

        $this->tenant->run(function () use ($invoice) {
            $data = $this->pdfData($invoice);
            $html = view('pdf.components._totals', $data)->render();
            $this->assertStringContainsString('Base TVA 20%', $html);
            $this->assertStringContainsString('Base TVA 10%', $html);
        });
    }

    /** @test */
    public function spanish_invoice_totals_never_show_a_base_line(): void
    {
        $this->tenant->run(fn () => CompanyProfile::first()->update([
            'country_code' => 'ES', 'currency' => 'EUR', 'locale' => 'es',
        ]));
        $invoice = $this->invoice([['price' => 100, 'vta' => 21]]);

        $this->tenant->run(function () use ($invoice) {
            $data = $this->pdfData($invoice);
            $html = view('pdf.components._totals', $data)->render();
            $this->assertStringNotContainsString('Base IVA', $html);
        });
    }

    // ── Rectification path generic tax breakdown (§17) ────────────

    /** @test */
    public function rectifying_a_mixed_rate_moroccan_invoice_preserves_every_rate(): void
    {
        $invoice = $this->issuedInvoice([['price' => 5000, 'vta' => 20], ['price' => 2000, 'vta' => 10]]);

        $response = $this->postJson($this->url('invoices/' . $invoice->uuid . '/rectify'), [
            'reason' => 'importe_incorrecto', 'rectification_type' => 'I',
        ])->assertOk();

        $this->tenant->run(function () use ($response, $invoice) {
            $rectification = Invoice::where('uuid', $response->json('rectification_uuid'))->first();

            $this->assertEquals(2, $rectification->taxLines()->count());
            $this->assertEqualsCanonicalizing(
                [20.0, 10.0],
                $rectification->taxLines->pluck('rate')->map(fn ($r) => (float) $r)->all()
            );

            $html = view('pdf.components._totals', $this->pdfData($rectification))->render();
            $this->assertStringContainsString('TVA 20%', $html);
            $this->assertStringContainsString('TVA 10%', $html, 'The 10% bucket must not silently disappear from a fresh rectification draft.');

            // The legacy bridge columns and aggregate totals stay a verbatim
            // copy of the original - VERI*FACTU's buildTaxBreakdown() must
            // never see a recomputed figure here (a Spanish rectification's
            // vta4/vta10/vta21 are what it reads at issuance time).
            $this->assertEquals((float) $invoice->sub_total, (float) $rectification->sub_total);
            $this->assertEquals((float) $invoice->vta, (float) $rectification->vta);
            $this->assertEquals((float) $invoice->total, (float) $rectification->total);
        });
    }

    /** @test */
    public function rectifying_an_invoice_with_an_exempt_line_preserves_the_exempt_treatment(): void
    {
        $invoice = $this->issuedInvoice([
            ['price' => 5000, 'vta' => 20],
            ['price' => 1000, 'vta' => 0, 'tax_treatment' => 'exempt'],
        ]);

        $response = $this->postJson($this->url('invoices/' . $invoice->uuid . '/rectify'), [
            'reason' => 'importe_incorrecto', 'rectification_type' => 'I',
        ])->assertOk();

        $this->tenant->run(function () use ($response) {
            $rectification = Invoice::where('uuid', $response->json('rectification_uuid'))->first();

            $exemptCart = $rectification->carts()->where('vta', 0)->first();
            $this->assertNotNull($exemptCart);
            $this->assertEquals('exempt', $exemptCart->tax_treatment, 'A rectified exempt line must not silently revert to taxable.');

            $exemptLine = $rectification->taxLines()->where('treatment', 'exempt')->first();
            $this->assertNotNull($exemptLine);
            $this->assertEquals(1000.0, (float) $exemptLine->taxable_base);

            $html = view('pdf.components._totals', $this->pdfData($rectification))->render();
            $this->assertStringContainsString('Exonéré', $html);
        });
    }

    // ── Spain/VERI*FACTU isolation on the PDF path (§13) ──────────

    /** @test */
    public function moroccan_invoice_pdf_never_reaches_the_spain_only_legacy_views(): void
    {
        $invoice = $this->invoice([['price' => 1000, 'vta' => 20]]);

        $this->tenant->run(function () use ($invoice) {
            $invoice->loadMissing('customer', 'carts');

            // Fake the exact hostnames the legacy invoices.tachua/invoices.yassine
            // views are gated on - proving it's the invoice's own country
            // (never 'ES' for this tenant), not merely the absence of a
            // matching Host header, that keeps Morocco off those views.
            foreach (['tachua.fakturalista.com', 'client1s.fakturalista.test'] as $host) {
                $rendered = null;
                View::composer('pdf.document', function () use (&$rendered) { $rendered = 'main'; });
                View::composer('invoices.tachua', function () use (&$rendered) { $rendered = 'tachua'; });
                View::composer('invoices.yassine', function () use (&$rendered) { $rendered = 'yassine'; });

                app()->instance('request', Request::create('/', 'GET', [], [], [], ['HTTP_HOST' => $host]));

                $controller = app(InvoiceController::class);
                $method = new \ReflectionMethod($controller, 'generateInvoicePdf');
                $method->setAccessible(true);
                $pdf = $method->invoke($controller, $invoice);

                $this->assertStringStartsWith('%PDF', $pdf);
                $this->assertEquals('main', $rendered, "Host {$host} must still render the main template for a Moroccan tenant.");
            }
        });
    }

    /** @test */
    public function moroccan_invoice_pdf_never_contains_verifactu_or_aeat_markers(): void
    {
        $invoice = $this->issuedInvoice([['price' => 5000, 'vta' => 20]]);

        $this->tenant->run(function () use ($invoice) {
            $data = $this->pdfData($invoice);
            $fullHtml = view('pdf.components._header', $data)->render()
                . view('pdf.components._addresses', $data)->render()
                . view('pdf.components._items', $data)->render()
                . view('pdf.components._totals', $data)->render()
                . view('pdf.components._footer', $data)->render();

            foreach (['VERI*FACTU', 'VERIFACTU', 'AEAT'] as $marker) {
                $this->assertStringNotContainsString($marker, $fullHtml);
            }
        });
    }

    // ── Quotes (§15) ───────────────────────────────────────────────

    /** @test */
    public function moroccan_quote_renders_mixed_taxable_and_exempt_lines_in_mad(): void
    {
        $payload = $this->payload([
            ['price' => 5000, 'vta' => 20],
            ['price' => 2000, 'vta' => 10],
            ['price' => 1000, 'vta' => 0, 'tax_treatment' => 'exempt'],
        ]);
        $this->postJson($this->url('quotes'), $payload)->assertOk();

        $this->tenant->run(function () {
            $quote = Quote::latest('id')->first();
            $data = $this->pdfData($quote, 'quote');
            $html = view('pdf.components._totals', $data)->render();

            foreach (['TVA 20%', 'TVA 10%', 'Exonéré', 'Total TVA', 'Total TTC'] as $label) {
                $this->assertStringContainsString($label, $html);
            }
            $this->assertStringNotContainsString('IVA', $html);
            $this->assertEquals(8000.0, (float) $data['subTotal']);
        });
    }
}
