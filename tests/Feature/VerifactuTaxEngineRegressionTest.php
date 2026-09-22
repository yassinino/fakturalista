<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceTaxLine;
use App\Models\Tenant;
use App\Services\InvoiceNumberingService;
use App\Services\Tax\DocumentCalculationService;
use App\Services\Verifactu\VerifactuChainService;
use App\Services\Verifactu\VerifactuHashService;
use App\Services\Verifactu\VerifactuXmlBuilder;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Morocco Phase 1C.1 §8 - docs/morocco-phase-1c1-generic-tax-foundation.md.
 *
 * Nothing under app/Services/Verifactu/* was touched in this phase. These
 * tests prove that claim empirically: a normal Spanish 4/10/21% invoice,
 * now produced by DocumentCalculationService instead of the old
 * client-trusted values, still yields byte-correct VERI*FACTU tax data
 * (Q) - and that a future/Moroccan rate the legacy vta4/vta10/vta21
 * columns can't represent is refused rather than silently misreported to
 * AEAT (R).
 */
class VerifactuTaxEngineRegressionTest extends TestCase
{
    protected Tenant $tenant;
    protected string $domain;
    protected CompanyProfile $company;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'verifactu.producer.name' => 'Fakturalista Test Producer',
            'verifactu.producer.nif' => null,
            'verifactu.producer.id_country' => 'MA',
            'verifactu.producer.id_type' => '06',
            'verifactu.producer.id' => 'RC-TEST-12345',
            'verifactu.system.name' => 'Fakturalista',
            'verifactu.system.id' => 'FK',
            'verifactu.system.version' => '1.0.0-test',
            'verifactu.system.only_verifactu' => true,
            'verifactu.system.multi_ot' => true,
            'verifactu.system.multiple_ot_in_use' => false,
        ]);

        $this->tenant = Tenant::create(['id' => 'test-vf-taxengine-' . uniqid()]);
        $this->domain = $this->tenant->id . '.fakturalista.test';
        $this->tenant->domains()->create(['domain' => $this->domain]);

        tenancy()->initialize($this->tenant);

        $this->company = CompanyProfile::create([
            'legal_name'                     => 'Empresa Ficticia de Pruebas SL',
            'tax_id'                         => '89890001K',
            'invoice_prefix'                 => 'INV',
            'onboarding_completed_at'        => now(),
            'verifactu_installation_number'  => 'TEST-INSTALL-1',
        ]);
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        $this->tenant->delete();
        parent::tearDown();
    }

    /**
     * Builds an invoice exactly the way InvoiceController::store() now
     * does (via DocumentCalculationService), rather than trusting raw
     * client-style values - this is the point of the test: prove the
     * NEW engine's output still satisfies VERI*FACTU.
     */
    private function makeCalculatedInvoice(array $cartLines): Invoice
    {
        $customer    = Customer::factory()->create(['tax_id' => 'B00000000']);
        $calculator  = app(DocumentCalculationService::class);
        $calculation = $calculator->calculate($cartLines);
        $legacy      = $calculation->legacySpanishRateAmounts();

        $invoice = Invoice::create([
            'uuid'            => Str::uuid()->toString(),
            'reference'       => 'DRAFT-TMP',
            'customer_id'     => $customer->id,
            'date'            => now()->toDateString(),
            'expiration_date' => now()->addDays(30)->toDateString(),
            'status'          => Invoice::STATUS_DRAFT,
            'sub_total'       => $calculation->subTotal,
            'discount_rate'   => 0,
            'discount_amount' => $calculation->discountAmount,
            'vta'             => $calculation->totalTax,
            'vta4'            => $legacy['vta4'],
            'vta10'           => $legacy['vta10'],
            'vta21'           => $legacy['vta21'],
            'total'           => $calculation->grandTotal,
            'descripcion_operacion' => 'Servicios de consultoría de prueba',
        ]);

        foreach ($cartLines as $index => $line) {
            Cart::create([
                'cartable_type' => 'App\Models\Invoice',
                'cartable_id'   => $invoice->id,
                'qty'           => $line['quantity'],
                'price'         => $line['unit_price'],
                'discount'      => $line['discount'] ?? 0,
                'vta'           => $line['tax_rate'],
                'total'         => $calculation->lines[$index]['taxable_base'],
            ]);
        }

        foreach ($calculation->taxBreakdown as $row) {
            InvoiceTaxLine::create([
                'invoice_id'   => $invoice->id,
                'rate'         => $row['rate'],
                'taxable_base' => $row['taxable_base'],
                'tax_amount'   => $row['tax_amount'],
            ]);
        }

        app(InvoiceNumberingService::class)->assignLegalNumber($invoice, $this->company);
        $invoice->status    = Invoice::STATUS_ISSUED;
        $invoice->issued_at = now();
        $invoice->save();

        return $invoice;
    }

    /** @test */
    public function q_a_normal_spanish_4_10_21_invoice_still_produces_correct_verifactu_xml(): void
    {
        $invoice = $this->makeCalculatedInvoice([
            ['quantity' => 1, 'unit_price' => 100, 'tax_rate' => 21],
            ['quantity' => 1, 'unit_price' => 200, 'tax_rate' => 10],
            ['quantity' => 1, 'unit_price' => 50,  'tax_rate' => 4],
        ]);

        $record = (new VerifactuChainService(new VerifactuHashService()))->recordAlta($invoice, $this->company);
        $xml    = app(VerifactuXmlBuilder::class)->build($record);

        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('sf', 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd');

        $detalles = $xpath->query('//sf:DetalleDesglose');
        $this->assertEquals(3, $detalles->length);

        $byRate = [];
        foreach ($detalles as $detalle) {
            $rate = $xpath->query('.//sf:TipoImpositivo', $detalle)->item(0)->textContent;
            $base = $xpath->query('.//sf:BaseImponibleOimporteNoSujeto', $detalle)->item(0)->textContent;
            $cuota = $xpath->query('.//sf:CuotaRepercutida', $detalle)->item(0)->textContent;
            $byRate[$rate] = ['base' => $base, 'cuota' => $cuota];
        }

        $this->assertEquals(['base' => '100.00', 'cuota' => '21.00'], $byRate['21']);
        $this->assertEquals(['base' => '200.00', 'cuota' => '20.00'], $byRate['10']);
        $this->assertEquals(['base' => '50.00', 'cuota' => '2.00'], $byRate['4']);
    }

    /** @test */
    public function r_a_rate_the_legacy_columns_cannot_represent_is_refused_not_silently_misreported(): void
    {
        // 21% (legacy-representable) + a 20% Moroccan-style line
        // (not representable in vta4/vta10/vta21). The aggregate vta
        // includes both; the legacy columns can only capture the 21%
        // one - buildTaxBreakdown()'s own pre-existing reconciliation
        // check must catch this mismatch and refuse, exactly as it
        // already does today for any exempt/0%/unsupported-rate line.
        $invoice = $this->makeCalculatedInvoice([
            ['quantity' => 1, 'unit_price' => 100, 'tax_rate' => 21],
            ['quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 20],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/no coincide|base imponible/i');

        (new VerifactuChainService(new VerifactuHashService()))->recordAlta($invoice, $this->company);
    }
}
