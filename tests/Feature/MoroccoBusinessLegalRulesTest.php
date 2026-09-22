<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Pdf\TemplateRendererService;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * Morocco Phase 1C.4 - docs/morocco-phase-1c4-business-legal-rules.md.
 *
 * Regression coverage for this phase's two audited-but-code-relevant
 * conclusions: (1) a Moroccan tenant that isn't VAT-liable can already
 * issue a fully non-VAT invoice without the system forcing the TVA 20%
 * system default on them, and (2) the existing (Spain-authored)
 * rectification mechanism produces a country-neutral PDF for a Moroccan
 * tenant - no Spanish reason/R-code vocabulary leaks onto the document.
 * Does not encode any unverified Moroccan legal assumption - both tests
 * assert mechanism behavior, not a specific legal requirement.
 */
class MoroccoBusinessLegalRulesTest extends TestCase
{
    private Tenant $tenant;
    private string $domain;
    private string $customerUuid;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create(['id' => 'test-1c4-' . uniqid()]);
        $this->domain = $this->tenant->id . '.fakturalista.test';
        $this->tenant->domains()->create(['domain' => $this->domain]);
        $user = $this->tenant->run(function () {
            CompanyProfile::create([
                'legal_name' => 'Phase 1C.4 Test SARL', 'country_code' => 'MA',
                'currency' => 'MAD', 'locale' => 'fr', 'invoice_prefix' => 'INV',
                'onboarding_completed_at' => now(),
            ]);
            $this->customerUuid = Customer::factory()->create()->uuid;
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
            'customer_id' => $this->customerUuid, 'date' => '2026-09-22',
            'expiration_date' => '2026-10-22', 'status' => 'draft', 'discount_rate' => 0,
            'sub_total' => 1, 'total' => 1, 'vta' => 1, 'vta21' => 999,
            'carts' => array_map(fn ($line) => $line + [
                'qty' => 1, 'discount' => 0, 'total' => 1, 'description' => 'Prestation de service',
            ], $lines),
        ];
    }

    // ── §8: non-VAT-liable Moroccan businesses ────────────────────

    /** @test */
    public function moroccan_tenant_can_issue_an_entirely_vat_free_invoice_without_the_system_forcing_tva(): void
    {
        // The seller is not VAT-registered - every line is explicitly
        // exempt/0%, the way the shared TaxSelect component submits a line
        // after a user picks "Exonéré". The system default (MA_TVA_20) is
        // never touched here and must never override an explicit choice.
        $response = $this->postJson($this->url('invoices'), $this->payload([
            ['price' => 5000, 'vta' => 0, 'tax_treatment' => 'exempt'],
            ['price' => 2000, 'vta' => 0, 'tax_treatment' => 'exempt'],
        ]));
        $response->assertOk();

        $invoice = $this->tenant->run(fn () => Invoice::latest('id')->first());
        $this->assertEquals(7000.0, (float) $invoice->sub_total);
        $this->assertEquals(0.0, (float) $invoice->vta, 'No VAT must be charged when every line is explicitly exempt.');
        $this->assertEquals(7000.0, (float) $invoice->total, 'TTC must equal HT when nothing is taxable - no VAT silently added.');

        $issue = $this->postJson($this->url('invoices/' . $invoice->uuid . '/issue'));
        $issue->assertOk('Issuing a wholly VAT-free Moroccan invoice must not be blocked.');

        $this->tenant->run(function () use ($invoice) {
            $data = [];
            View::composer('pdf.document', function ($view) use (&$data) { $data = $view->getData(); });
            $pdf = app(TemplateRendererService::class)->render($invoice->fresh(), 'invoice');
            $this->assertStringStartsWith('%PDF', $pdf);

            $html = view('pdf.components._items', $data)->render() . view('pdf.components._totals', $data)->render();
            $this->assertStringContainsString('Exonéré', $html);
            $this->assertStringNotContainsString('TVA 20%', $html, 'A non-VAT-liable tenant must never see the system default rate forced onto their invoice.');
        });
    }

    /** @test */
    public function saving_an_exempt_tenant_default_does_not_retroactively_change_an_already_issued_invoice(): void
    {
        // A tenant that later registers as VAT-exempt (or vice versa) and
        // changes Settings must not rewrite the meaning of invoices already
        // issued under the previous default - same historical-safety
        // guarantee already proven for identity in Phase 1B/1C.3, checked
        // here specifically for the tenant tax default.
        $response = $this->postJson($this->url('invoices'), $this->payload([
            ['price' => 1000, 'vta' => 20],
        ]));
        $response->assertOk();
        $invoice = $this->tenant->run(fn () => Invoice::latest('id')->first());
        $this->postJson($this->url('invoices/' . $invoice->uuid . '/issue'))->assertOk();
        $before = $this->tenant->run(fn () => $invoice->fresh()->load('carts', 'taxLines')->toArray());

        $profile = $this->getJson($this->url('settings'))->assertOk()->json('settings');
        $this->putJson($this->url('settings'), array_merge($profile, [
            'default_tax_code' => 'MA_EXEMPT',
        ]))->assertOk();

        $this->tenant->run(function () use ($invoice, $before) {
            $this->assertSame($before, $invoice->fresh()->load('carts', 'taxLines')->toArray());
        });
    }

    // ── §7: rectification mechanism is country-neutral in output ──

    /** @test */
    public function rectifying_a_moroccan_invoice_never_leaks_spanish_rectification_vocabulary_onto_the_pdf(): void
    {
        $this->postJson($this->url('invoices'), $this->payload([['price' => 5000, 'vta' => 20]]))->assertOk();
        $invoice = $this->tenant->run(fn () => Invoice::latest('id')->first());
        $this->postJson($this->url('invoices/' . $invoice->uuid . '/issue'))->assertOk();

        $response = $this->postJson($this->url('invoices/' . $invoice->uuid . '/rectify'), [
            'reason' => 'importe_incorrecto', 'rectification_type' => 'I',
        ]);
        $response->assertOk();

        $this->tenant->run(function () use ($response) {
            $rectification = Invoice::where('uuid', $response->json('rectification_uuid'))->first();
            $this->assertEquals('R1', $rectification->invoice_type, 'The AEAT code is still stored internally - it must simply never reach the document.');

            $data = [];
            View::composer('pdf.document', function ($view) use (&$data) { $data = $view->getData(); });
            app(TemplateRendererService::class)->render($rectification, 'invoice');

            $fullHtml = view('pdf.components._header', $data)->render()
                . view('pdf.components._addresses', $data)->render()
                . view('pdf.components._items', $data)->render()
                . view('pdf.components._totals', $data)->render()
                . view('pdf.components._footer', $data)->render();

            foreach (['R1', 'R2', 'R3', 'R4', 'R5', 'rectificativa', 'Rectificativa', 'AEAT', 'VERI*FACTU'] as $marker) {
                $this->assertStringNotContainsString($marker, $fullHtml);
            }
        });
    }
}
