<?php

namespace Tests\Feature;

use App\Models\{CompanyProfile, Customer, Family, Invoice, Quote, Tenant, User};
use App\Services\Pdf\TemplateRendererService;
use App\Services\QuoteToInvoiceService;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class CountryTaxConfigurationTest extends TestCase
{
    private Tenant $tenant;
    private string $domain;
    private string $customerUuid;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create(['id' => 'test-tax-config-' . uniqid()]);
        $this->domain = $this->tenant->id . '.fakturalista.test';
        $this->tenant->domains()->create(['domain' => $this->domain]);
        $user = $this->tenant->run(function () {
            CompanyProfile::create([
                'legal_name' => 'Tax configuration test', 'country_code' => 'MA',
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

    private function country(string $country): void
    {
        $this->tenant->run(fn () => CompanyProfile::first()->update([
            'country_code' => $country, 'currency' => $country === 'MA' ? 'MAD' : 'EUR',
            'locale' => $country === 'MA' ? 'fr' : 'es',
        ]));
    }

    private function payload(array $lines): array
    {
        return [
            'customer_id' => $this->customerUuid, 'date' => '2026-09-21',
            'expiration_date' => '2026-10-21', 'status' => 'draft', 'discount_rate' => 0,
            // Deliberately untrustworthy client totals must be ignored.
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

    private function saveDefault(string $code, string $country = 'MA')
    {
        $profile = $this->getJson($this->url('settings'))->assertOk()->json('settings');
        return $this->putJson($this->url('settings'), array_merge($profile, [
            'country_code' => $country, 'default_tax_code' => $code,
        ]));
    }

    private function pdfData(object $document, string $type = 'invoice'): array
    {
        $data = [];
        View::composer('pdf.document', function ($view) use (&$data) { $data = $view->getData(); });
        $pdf = app(TemplateRendererService::class)->render($document, $type);
        $this->assertStringStartsWith('%PDF', $pdf);
        return $data;
    }

    public static function countries(): array
    {
        return [
            'Morocco' => ['MA', [20, 10, 0], ['taxable', 'taxable', 'exempt'], ['TVA 20%', 'TVA 10%', 'Exonéré'], 'MA_TVA_20'],
            'Spain' => ['ES', [21, 10, 4], ['taxable', 'taxable', 'taxable'], ['IVA 21%', 'IVA 10%', 'IVA 4%'], 'ES_IVA_21'],
        ];
    }

    /** @dataProvider countries */
    public function test_presets_are_isolated_by_tenant_country($country, $rates, $treatments, $labels, $default): void
    {
        $this->country($country);
        $data = $this->getJson($this->url('tax-presets'))->assertOk()->json();
        $this->assertEquals($rates, array_column($data['presets'], 'rate'));
        $this->assertSame($treatments, array_column($data['presets'], 'treatment'));
        $this->assertSame($labels, array_column($data['presets'], 'label'));
        $this->assertSame($default, $data['default_code']);
        $this->assertSame([$country], array_values(array_unique(array_column($data['presets'], 'country_code'))));
    }

    public static function invoiceExamples(): array
    {
        return [
            'MA 20%' => ['MA', [['price' => 5000, 'vta' => 20]], 5000, 1000, 6000],
            'MA 10%' => ['MA', [['price' => 5000, 'vta' => 10]], 5000, 500, 5500],
            'MA exempt' => ['MA', [['price' => 5000, 'vta' => 0, 'tax_treatment' => 'exempt']], 5000, 0, 5000],
            'MA mixed' => ['MA', [['price' => 1000, 'vta' => 20], ['price' => 1000, 'vta' => 10]], 2000, 300, 2300],
            'ES 21%' => ['ES', [['price' => 5000, 'vta' => 21]], 5000, 1050, 6050],
            'ES mixed' => ['ES', [['price' => 1000, 'vta' => 4], ['price' => 1000, 'vta' => 10], ['price' => 1000, 'vta' => 21]], 3000, 350, 3350],
        ];
    }

    /** @dataProvider invoiceExamples */
    public function test_server_calculates_and_persists_each_example($country, $lines, $subtotal, $tax, $total): void
    {
        $this->country($country);
        $invoice = $this->invoice($lines);
        $this->tenant->run(function () use ($invoice, $lines, $subtotal, $tax, $total) {
            $this->assertEquals($subtotal, $invoice->sub_total);
            $this->assertEquals($tax, $invoice->vta);
            $this->assertEquals($total, $invoice->total);
            $this->assertCount(count($lines), $invoice->taxLines);
            foreach ($lines as $line) {
                $row = $invoice->taxLines->firstWhere('rate', $line['vta']);
                $this->assertSame($line['tax_treatment'] ?? 'taxable', $row->treatment);
                $this->assertEquals($line['price'], $row->taxable_base);
                $this->assertEquals($line['price'] * $line['vta'] / 100, $row->tax_amount);
            }
            $this->assertEquals($lines[0]['tax_treatment'] ?? 'taxable', $invoice->carts->first()->tax_treatment);
            if (count($lines) === 1 && $lines[0]['vta'] === 20) {
                $this->assertEquals(0, $invoice->vta4 + $invoice->vta10 + $invoice->vta21);
            }
        });
    }

    public function test_taxable_zero_and_exempt_zero_remain_separate_through_edit_and_duplicate(): void
    {
        $lines = [['price' => 1000, 'vta' => 0], ['price' => 5000, 'vta' => 0, 'tax_treatment' => 'exempt']];
        $invoice = $this->invoice($lines);
        $edit = $this->getJson($this->url('invoices/' . $invoice->uuid . '/edit'))->assertOk()->json('invoice');
        $this->assertSame(['taxable', 'exempt'], array_column($edit['carts'], 'tax_treatment'));
        $this->putJson($this->url('invoices/' . $invoice->uuid), $this->payload($edit['carts']))->assertOk();
        $uuid = $this->postJson($this->url('invoices/' . $invoice->uuid . '/duplicate'))->assertOk()->json('duplicate_uuid');
        $this->tenant->run(function () use ($invoice, $uuid) {
            foreach ([$invoice->fresh(), Invoice::where('uuid', $uuid)->first()] as $doc) {
                $this->assertCount(2, $doc->taxLines);
                $this->assertSame(['exempt', 'taxable'], $doc->taxLines->pluck('treatment')->sort()->values()->all());
                $this->assertEquals(6000, $doc->total);
            }
        });
    }

    public function test_quote_presets_and_exempt_treatment_survive_edit_and_conversion(): void
    {
        $presets = $this->getJson($this->url('tax-presets'))->assertOk()->json('presets');
        $lines = array_map(fn ($p) => ['price' => 1000, 'vta' => $p['rate'], 'tax_treatment' => $p['treatment']], $presets);
        $this->postJson($this->url('quotes'), $this->payload($lines))->assertOk();
        $quote = $this->tenant->run(fn () => Quote::latest('id')->first());
        $edit = $this->getJson($this->url('quotes/' . $quote->uuid . '/edit'))->assertOk()->json('quote');
        $this->putJson($this->url('quotes/' . $quote->uuid), $this->payload($edit['carts']))->assertOk();
        $this->tenant->run(function () use ($quote) {
            $quote = $quote->fresh();
            $this->assertEquals(3300, $quote->total);
            $this->assertSame(['taxable', 'taxable', 'exempt'], $quote->carts->pluck('tax_treatment')->all());
            $invoice = app(QuoteToInvoiceService::class)->convert($quote);
            $this->assertEquals(3300, $invoice->total);
            $this->assertSame('exempt', $invoice->taxLines->firstWhere('rate', 0)->treatment);
        });
    }

    public function test_settings_change_affects_only_future_defaults_and_preserves_issued_invoice_and_pdf(): void
    {
        $invoice = $this->invoice([['price' => 5000, 'vta' => 0, 'tax_treatment' => 'exempt']]);
        $this->postJson($this->url('invoices/' . $invoice->uuid . '/issue'))->assertOk();
        $before = $this->tenant->run(fn () => $invoice->fresh()->load('carts', 'taxLines')->toArray());
        $this->saveDefault('MA_TVA_10')->assertOk();
        $this->getJson($this->url('tax-presets'))->assertOk()->assertJsonPath('default_code', 'MA_TVA_10');
        $this->putJson($this->url('invoices/' . $invoice->uuid), $this->payload([['price' => 1, 'vta' => 20]]))->assertForbidden();
        $this->tenant->run(function () use ($invoice, $before) {
            $fresh = $invoice->fresh()->load('carts', 'taxLines');
            $data = $this->pdfData($fresh);
            $this->assertSame('exempt', $data['taxGroups'][0]['treatment']);
            $this->assertEquals(5000, $data['grandTotal']);
            $this->assertSame($before, $invoice->fresh()->load('carts', 'taxLines')->toArray());
        });
    }

    public function test_changing_default_preserves_draft_lines_and_rejects_another_countrys_preset(): void
    {
        $invoice = $this->invoice([['price' => 5000, 'vta' => 20]]);
        $before = $this->tenant->run(fn () => $invoice->load('carts', 'taxLines')->toArray());
        $this->saveDefault('MA_EXEMPT')->assertOk();
        $this->saveDefault('ES_IVA_21')->assertUnprocessable()->assertJsonValidationErrors('default_tax_code');
        $this->getJson($this->url('tax-presets'))->assertOk()->assertJsonPath('default_code', 'MA_EXEMPT');
        $this->tenant->run(fn () => $this->assertSame($before, $invoice->fresh()->load('carts', 'taxLines')->toArray()));
    }

    public function test_stale_default_from_another_country_is_not_used(): void
    {
        $this->tenant->run(fn () => CompanyProfile::first()->update(['default_tax_code' => 'ES_IVA_21']));
        $this->getJson($this->url('tax-presets'))->assertOk()->assertJsonPath('default_code', 'MA_TVA_20');
        $this->country('US');
        $this->getJson($this->url('tax-presets'))->assertOk()->assertJsonPath('presets', [])->assertJsonPath('default_code', null);
    }

    public function test_item_defaults_and_overrides_are_available_to_both_document_forms(): void
    {
        $this->saveDefault('MA_TVA_10')->assertOk();
        $family = $this->tenant->run(fn () => Family::create(['name' => 'Services']));
        $payload = ['name' => 'Web development', 'type' => 1, 'unite' => 'pc', 'family_id' => $family->id, 'sales_price' => 5000];
        $this->postJson($this->url('items'), $payload)->assertCreated();
        $item = $this->getJson($this->url('items'))->assertOk()->json('items.0');
        $this->assertEquals(10, $item['vta']);
        $this->assertSame('taxable', $item['tax_treatment']);
        $this->putJson($this->url('items/' . $item['uuid']), $payload + ['vta' => 0, 'tax_treatment' => 'exempt'])->assertOk();
        $this->saveDefault('MA_TVA_20')->assertOk();
        // An unrelated edit must preserve the service's saved exemption.
        $this->putJson($this->url('items/' . $item['uuid']), $payload)->assertOk();
        $item = $this->getJson($this->url('items/' . $item['uuid'] . '/edit'))->assertOk()->json('item');
        $this->assertEquals(0, $item['vta']);
        $this->assertSame('exempt', $item['tax_treatment']);
        $line = ['item_id' => $item['id'], 'price' => $item['sales_price'], 'vta' => $item['vta'], 'tax_treatment' => $item['tax_treatment']];
        $invoice = $this->invoice([$line]);
        $this->postJson($this->url('quotes'), $this->payload([$line]))->assertOk();
        $this->tenant->run(function () use ($invoice, $item) {
            foreach ([$invoice, Quote::latest('id')->first()] as $doc) {
                $this->assertEquals($item['id'], $doc->carts->first()->item_id);
                $this->assertSame('exempt', $doc->carts->first()->tax_treatment);
                $this->assertEquals(5000, $doc->total);
            }
        });
    }

    /** @dataProvider countries */
    public function test_pdf_country_labels_and_stored_breakdown($country): void
    {
        $this->country($country);
        $rates = $country === 'MA' ? [20, 10, 0] : [21, 10, 4];
        $invoice = $this->invoice(array_map(fn ($rate) => [
            'price' => 1000, 'vta' => $rate, 'tax_treatment' => $rate === 0 ? 'exempt' : 'taxable',
        ], $rates));
        $this->tenant->run(function () use ($invoice, $country) {
            $data = $this->pdfData($invoice);
            $html = view('pdf.components._totals', $data)->render();
            $items = view('pdf.components._items', $data)->render();
            if ($country === 'MA') {
                foreach (['Sous-total HT', 'TVA 20%', 'TVA 10%', 'Exonéré', 'Total TVA', 'Total TTC'] as $label) {
                    $this->assertStringContainsString($label, $html);
                }
                $this->assertStringContainsString('Exonéré', $items);
                $this->assertEquals([0, 100, 200], array_column($data['taxGroups'], 'amount'));
                $this->assertEquals(3300, $data['grandTotal']);
                $this->assertStringNotContainsString('IVA', $html);
            } else {
                foreach (['IVA 4%', 'IVA 10%', 'IVA 21%'] as $label) $this->assertStringContainsString($label, $html);
                $this->assertEquals([40, 100, 210], array_column($data['taxGroups'], 'amount'));
                $this->assertStringNotContainsString('TVA', $html);
            }
            $this->assertEquals([1000, 1000, 1000], array_column($data['taxGroups'], 'base'));
        });
    }

    public function test_quote_pdf_uses_discounted_bases_from_authoritative_calculator(): void
    {
        $payload = $this->payload([['price' => 5000, 'vta' => 20, 'discount' => 50]]);
        $payload['discount_rate'] = 10;
        $this->postJson($this->url('quotes'), $payload)->assertOk();
        $this->tenant->run(function () {
            $quote = Quote::latest('id')->first();
            $data = $this->pdfData($quote, 'quote');
            $this->assertEquals(2250, $data['taxGroups'][0]['base']);
            $this->assertEquals(450, $data['taxGroups'][0]['amount']);
            $this->assertEquals(2700, $data['grandTotal']);
        });
    }

    public function test_legacy_issued_invoice_keeps_its_stored_tax_without_recalculation(): void
    {
        $invoice = $this->invoice([['price' => 100, 'vta' => 21]]);
        $this->tenant->run(function () use ($invoice) {
            $invoice->taxLines()->delete();
            $invoice->update(['status' => Invoice::STATUS_ISSUED, 'issued_at' => now(), 'vta' => 19.99, 'vta21' => 19.99, 'total' => 119.99]);
        });
        $this->saveDefault('MA_EXEMPT')->assertOk();
        $this->tenant->run(function () use ($invoice) {
            $before = $invoice->fresh()->load('carts', 'taxLines')->toArray();
            $data = $this->pdfData($invoice->fresh());
            $this->assertEquals(19.99, $data['taxGroups'][0]['amount']);
            $this->assertEquals(119.99, $data['grandTotal']);
            $this->assertSame($before, $invoice->fresh()->load('carts', 'taxLines')->toArray());
        });
    }

    public function test_issued_pdf_tax_identity_survives_a_later_country_and_default_change(): void
    {
        $invoice = $this->invoice([['price' => 5000, 'vta' => 20]]);
        $this->postJson($this->url('invoices/' . $invoice->uuid . '/issue'))->assertOk();
        $this->saveDefault('ES_IVA_4', 'ES')->assertOk();
        $this->getJson($this->url('tax-presets'))->assertOk()->assertJsonPath('default_code', 'ES_IVA_4');
        $this->tenant->run(function () use ($invoice) {
            $data = $this->pdfData($invoice->fresh());
            $this->assertTrue($data['isMoroccanTax']);
            $this->assertEquals(1000, $data['totalTaxAmount']);
            $this->assertEquals(6000, $data['grandTotal']);
            $this->assertStringContainsString('TVA 20%', view('pdf.components._totals', $data)->render());
        });
    }

    public function test_new_items_can_inherit_an_exempt_tenant_default(): void
    {
        $this->saveDefault('MA_EXEMPT')->assertOk();
        $family = $this->tenant->run(fn () => Family::create(['name' => 'Services']));
        $this->postJson($this->url('items'), [
            'name' => 'Exempt service', 'family_id' => $family->id, 'type' => 1, 'unite' => 'pc', 'sales_price' => 5000,
        ])->assertCreated();
        $item = $this->getJson($this->url('items'))->assertOk()->json('items.0');
        $this->assertEquals(0, $item['vta']);
        $this->assertSame('exempt', $item['tax_treatment']);
    }

    public function test_exemption_with_positive_rate_is_rejected_for_invoices_quotes_and_items(): void
    {
        $payload = $this->payload([['price' => 5000, 'vta' => 20, 'tax_treatment' => 'exempt']]);
        foreach (['invoices', 'quotes'] as $path) {
            $this->postJson($this->url($path), $payload)->assertUnprocessable()->assertJsonValidationErrors('carts.0.vta');
        }
        $family = $this->tenant->run(fn () => Family::create(['name' => 'Services']));
        $this->postJson($this->url('items'), [
            'name' => 'Service', 'family_id' => $family->id, 'type' => 1, 'vta' => 20, 'tax_treatment' => 'exempt',
        ])->assertUnprocessable()->assertJsonValidationErrors('vta');
    }
}
