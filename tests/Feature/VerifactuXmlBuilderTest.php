<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\InvoiceNumberingService;
use App\Services\InvoiceRectificationService;
use App\Services\Verifactu\VerifactuChainService;
use App\Services\Verifactu\VerifactuHashService;
use App\Services\Verifactu\VerifactuXmlBuilder;
use App\Services\Verifactu\VerifactuXmlValidator;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * End-to-end: real tenant DB -> VerifactuChainService (Phase 2B, extended
 * in Phase 2C) -> VerifactuXmlBuilder -> VerifactuXmlValidator against the
 * official vendored schema. All identities below are clearly fictional
 * (see class docblocks' data-security notes) - never real taxpayer data.
 *
 * As with VerifactuChainServiceTest, nothing here is wired into
 * InvoiceController::issue()/cancel() - these tests call the services
 * directly, exactly as a future wiring step eventually will.
 */
class VerifactuXmlBuilderTest extends TestCase
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

        $this->tenant = Tenant::create(['id' => 'test-verifactu-xml-' . uniqid()]);
        $this->domain = 'test-verifactu-xml-' . uniqid() . '.fakturalista.test';
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

    private function chainService(): VerifactuChainService
    {
        return new VerifactuChainService(new VerifactuHashService());
    }

    private function builder(): VerifactuXmlBuilder
    {
        return new VerifactuXmlBuilder();
    }

    private function validator(): VerifactuXmlValidator
    {
        return new VerifactuXmlValidator();
    }

    private function issuedInvoice(array $overrides = [], ?Customer $customer = null): Invoice
    {
        $customer ??= Customer::factory()->create(['company_name' => 'Cliente Ficticio SA', 'tax_id' => 'B00000000']);

        $invoice = Invoice::create(array_merge([
            'uuid'                  => Str::uuid()->toString(),
            'reference'             => 'DRAFT-TMP',
            'customer_id'           => $customer->id,
            'date'                  => now()->toDateString(),
            'expiration_date'       => now()->addDays(30)->toDateString(),
            'status'                => Invoice::STATUS_DRAFT,
            'sub_total'             => 100.00,
            'total'                 => 121.00,
            'vta'                   => 21.00,
            'vta4'                  => 0,
            'vta10'                 => 0,
            'vta21'                 => 21.00,
            'discount_rate'         => 0,
            'discount_amount'       => 0,
            'descripcion_operacion' => 'Servicios de consultoría de prueba',
        ], $overrides));

        app(InvoiceNumberingService::class)->assignLegalNumber($invoice, $this->company);
        $invoice->status    = Invoice::STATUS_ISSUED;
        $invoice->issued_at = now();
        $invoice->save();

        return $invoice;
    }

    // ── Alta ─────────────────────────────────────────────────

    /** @test */
    public function alta_xml_is_valid_against_the_official_schema(): void
    {
        $invoice = $this->issuedInvoice();
        $record  = $this->chainService()->recordAlta($invoice, $this->company);

        $xml = $this->builder()->buildAlta($record);

        $this->validator()->validate($xml);
        $this->addToAssertionCount(1);
    }

    /** @test */
    public function alta_xml_uses_the_official_default_namespace(): void
    {
        $invoice = $this->issuedInvoice();
        $record  = $this->chainService()->recordAlta($invoice, $this->company);

        $xml = $this->builder()->buildAlta($record);
        $dom = new \DOMDocument();
        $dom->loadXML($xml);

        $this->assertEquals(
            'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd',
            $dom->documentElement->namespaceURI
        );
        $this->assertEquals('RegistroAlta', $dom->documentElement->localName);
    }

    /** @test */
    public function first_alta_uses_primer_registro_not_registro_anterior(): void
    {
        $invoice = $this->issuedInvoice();
        $record  = $this->chainService()->recordAlta($invoice, $this->company);

        $xml = $this->builder()->buildAlta($record);

        $this->assertStringContainsString('<PrimerRegistro>S</PrimerRegistro>', $xml);
        $this->assertStringNotContainsString('<RegistroAnterior>', $xml);
    }

    /** @test */
    public function chained_alta_references_the_previous_records_hash(): void
    {
        $invoice1 = $this->issuedInvoice();
        $record1  = $this->chainService()->recordAlta($invoice1, $this->company);

        $invoice2 = $this->issuedInvoice();
        $record2  = $this->chainService()->recordAlta($invoice2, $this->company);

        $xml = $this->builder()->buildAlta($record2);

        $this->validator()->validate($xml);
        $this->assertStringContainsString('<RegistroAnterior>', $xml);
        $this->assertStringContainsString('<Huella>' . $record1->huella . '</Huella>', $xml);
        $this->assertStringContainsString('<NumSerieFactura>' . $invoice1->reference . '</NumSerieFactura>', $xml);
    }

    /** @test */
    public function decimal_amounts_use_exactly_two_decimal_places(): void
    {
        $invoice = $this->issuedInvoice(['vta21' => 21, 'vta' => 21, 'total' => 121]);
        $record  = $this->chainService()->recordAlta($invoice, $this->company);

        $xml = $this->builder()->buildAlta($record);

        $this->assertStringContainsString('<CuotaTotal>21.00</CuotaTotal>', $xml);
        $this->assertStringContainsString('<ImporteTotal>121.00</ImporteTotal>', $xml);
        $this->assertStringContainsString('<BaseImponibleOimporteNoSujeto>100.00</BaseImponibleOimporteNoSujeto>', $xml);
    }

    /** @test */
    public function multi_rate_invoice_produces_one_detalledesglose_per_rate(): void
    {
        $invoice = $this->issuedInvoice(['vta4' => 0.40, 'vta10' => 0, 'vta21' => 21, 'vta' => 21.40, 'total' => 131.40]);
        $record  = $this->chainService()->recordAlta($invoice, $this->company);

        $xml = $this->builder()->buildAlta($record);
        $this->validator()->validate($xml);

        $this->assertEquals(2, substr_count($xml, '<DetalleDesglose>'));
        $this->assertStringContainsString('<TipoImpositivo>4</TipoImpositivo>', $xml);
        $this->assertStringContainsString('<TipoImpositivo>21</TipoImpositivo>', $xml);
    }

    /** @test */
    public function f1_invoice_with_customer_nif_includes_destinatarios(): void
    {
        $invoice = $this->issuedInvoice();
        $record  = $this->chainService()->recordAlta($invoice, $this->company);

        $xml = $this->builder()->buildAlta($record);

        $this->assertStringContainsString('<Destinatarios>', $xml);
        $this->assertStringContainsString('<NIF>B00000000</NIF>', $xml);
    }

    /** @test */
    public function f2_invoice_without_customer_nif_omits_destinatarios_and_stays_valid(): void
    {
        $customerWithoutNif = Customer::factory()->withoutTaxId()->create(['company_name' => 'Cliente Anónimo']);
        $invoice = $this->issuedInvoice(['invoice_type' => Invoice::TYPE_F2], $customerWithoutNif);
        $record  = $this->chainService()->recordAlta($invoice, $this->company);

        $xml = $this->builder()->buildAlta($record);

        $this->assertStringNotContainsString('<Destinatarios>', $xml);
        $this->validator()->validate($xml);
        $this->addToAssertionCount(1);
    }

    /** @test */
    public function spanish_and_special_characters_round_trip_correctly(): void
    {
        $invoice = $this->issuedInvoice([
            'descripcion_operacion' => 'Diseño & maquetación de página web <promoción> "Otoño"',
        ]);
        $record = $this->chainService()->recordAlta($invoice, $this->company);

        $xml = $this->builder()->buildAlta($record);
        $this->validator()->validate($xml);

        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        $descripcion = $dom->getElementsByTagNameNS(
            'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd',
            'DescripcionOperacion'
        )->item(0)->textContent;

        $this->assertEquals('Diseño & maquetación de página web <promoción> "Otoño"', $descripcion);
    }

    /** @test */
    public function output_is_deterministic_for_the_same_record(): void
    {
        $invoice = $this->issuedInvoice();
        $record  = $this->chainService()->recordAlta($invoice, $this->company);

        $xml1 = $this->builder()->buildAlta($record->fresh(['taxDetails', 'previousRecord']));
        $xml2 = $this->builder()->buildAlta($record->fresh(['taxDetails', 'previousRecord']));

        $this->assertEquals($xml1, $xml2);
    }

    /** @test */
    public function missing_producer_configuration_throws_instead_of_generating_incomplete_xml(): void
    {
        config(['verifactu.producer.name' => null]);

        $invoice = $this->issuedInvoice();
        $record  = $this->chainService()->recordAlta($invoice, $this->company);

        $this->expectException(\RuntimeException::class);
        $this->builder()->buildAlta($record);
    }

    // ── Anulación ─────────────────────────────────────────────

    /** @test */
    public function anulacion_xml_is_valid_and_uses_anulada_suffixed_fields(): void
    {
        $invoice = $this->issuedInvoice();
        $this->chainService()->recordAlta($invoice, $this->company);
        $anulacion = $this->chainService()->recordAnulacion($invoice, $this->company);

        $xml = $this->builder()->buildAnulacion($anulacion);

        $this->validator()->validate($xml);
        $this->assertStringContainsString('<IDEmisorFacturaAnulada>', $xml);
        $this->assertStringContainsString('<NumSerieFacturaAnulada>' . $invoice->reference . '</NumSerieFacturaAnulada>', $xml);
        // RegistroFacturacionAnulacionType has no NombreRazonEmisor/Desglose at all.
        $this->assertStringNotContainsString('<NombreRazonEmisor>', $xml);
        $this->assertStringNotContainsString('<Desglose>', $xml);
    }

    /** @test */
    public function build_dispatches_to_the_correct_shape_based_on_tipo_registro(): void
    {
        $invoice   = $this->issuedInvoice();
        $alta      = $this->chainService()->recordAlta($invoice, $this->company);
        $anulacion = $this->chainService()->recordAnulacion($invoice, $this->company);

        $this->assertStringContainsString('<RegistroAlta', $this->builder()->build($alta));
        $this->assertStringContainsString('<RegistroAnulacion', $this->builder()->build($anulacion));
    }

    // ── Rectificativas ──────────────────────────────────────────

    /** @test */
    public function rectificativa_xml_includes_tipo_rectificativa_and_facturas_rectificadas(): void
    {
        $original = $this->issuedInvoice();
        $this->chainService()->recordAlta($original, $this->company);

        $rectificationService = new InvoiceRectificationService(app(InvoiceNumberingService::class));
        $rectification = $rectificationService->createRectification(
            $original,
            InvoiceRectificationService::REASON_IMPORTE_INCORRECTO,
            Invoice::RECTIFICATION_MODE_DIFERENCIAS
        );
        $rectification->descripcion_operacion = 'Corrección de importe de la factura ' . $original->reference;
        $rectification->save();
        app(InvoiceNumberingService::class)->assignRectificationNumber($rectification, $this->company);
        $rectification->status    = Invoice::STATUS_ISSUED;
        $rectification->issued_at = now();
        $rectification->save();

        $record = $this->chainService()->recordAlta($rectification, $this->company);
        $xml    = $this->builder()->buildAlta($record);

        $this->validator()->validate($xml);
        $this->assertStringContainsString('<TipoRectificativa>I</TipoRectificativa>', $xml);
        $this->assertStringContainsString('<FacturasRectificadas>', $xml);
        $this->assertStringContainsString('<NumSerieFactura>' . $original->reference . '</NumSerieFactura>', $xml);
        $this->assertStringContainsString('<TipoFactura>' . Invoice::TYPE_R1 . '</TipoFactura>', $xml);
        // Orden HAC/1177/2024's field table + the XSD's own annotation tie
        // ImporteRectificacion to substitutive (S) rectificativas only.
        $this->assertStringNotContainsString('<ImporteRectificacion>', $xml);
    }

    /** @test */
    public function rectificativa_sustitucion_uses_s_code(): void
    {
        $original = $this->issuedInvoice();
        $this->chainService()->recordAlta($original, $this->company);

        $rectificationService = new InvoiceRectificationService(app(InvoiceNumberingService::class));
        $rectification = $rectificationService->createRectification(
            $original,
            InvoiceRectificationService::REASON_ERROR_DATOS,
            Invoice::RECTIFICATION_MODE_SUSTITUCION
        );
        $rectification->descripcion_operacion = 'Sustitución completa de la factura ' . $original->reference;
        $rectification->save();
        app(InvoiceNumberingService::class)->assignRectificationNumber($rectification, $this->company);
        $rectification->status    = Invoice::STATUS_ISSUED;
        $rectification->issued_at = now();
        $rectification->save();

        $record = $this->chainService()->recordAlta($rectification, $this->company);
        $xml    = $this->builder()->buildAlta($record);

        $this->validator()->validate($xml);
        $this->assertStringContainsString('<TipoRectificativa>S</TipoRectificativa>', $xml);
        // ImporteRectificacion (DesgloseRectificacionType) carries the
        // ORIGINAL's own base/cuota being substituted (Orden HAC/1177/2024
        // field table + the XSD's "Base y Cuota sustituida" annotation) -
        // $original here is 100.00 base / 21.00 cuota (issuedInvoice()'s
        // defaults).
        $this->assertStringContainsString('<ImporteRectificacion>', $xml);
        $this->assertStringContainsString('<BaseRectificada>100.00</BaseRectificada>', $xml);
        $this->assertStringContainsString('<CuotaRectificada>21.00</CuotaRectificada>', $xml);
    }

    // ── Foreign customers / IDOtro ────────────────────────────────

    /** @test */
    public function foreign_customer_with_billing_country_uses_idotro_with_codigo_pais(): void
    {
        $france   = \App\Models\Country::create(['name' => 'Francia', 'code' => 'FR']);
        $customer = Customer::factory()->foreignTaxId('03', 'FR-PASSPORT-99')->create([
            'company_name'       => 'Client Français SARL',
            'billing_country_id' => $france->id,
        ]);
        $invoice = $this->issuedInvoice([], $customer);
        $record  = $this->chainService()->recordAlta($invoice, $this->company);

        $xml = $this->builder()->buildAlta($record);
        $this->validator()->validate($xml);

        $this->assertStringContainsString('<IDOtro>', $xml);
        $this->assertStringContainsString('<CodigoPais>FR</CodigoPais>', $xml);
        $this->assertStringContainsString('<IDType>03</IDType>', $xml);
        $this->assertStringContainsString('<ID>FR-PASSPORT-99</ID>', $xml);
        $this->assertStringNotContainsString('<NIF>', $xml);
    }

    /** @test */
    public function foreign_customer_without_billing_country_omits_codigo_pais_but_stays_valid(): void
    {
        $customer = Customer::factory()->foreignTaxId('07', 'NO-REGISTRO-1')->create([
            'company_name'       => 'Cliente Sin País SL',
            'billing_country_id' => null,
        ]);
        $invoice = $this->issuedInvoice([], $customer);
        $record  = $this->chainService()->recordAlta($invoice, $this->company);

        $xml = $this->builder()->buildAlta($record);

        $this->validator()->validate($xml);
        // Exactly one CodigoPais in the whole document - the software
        // producer's own (config, Morocco) - none for this destinatario,
        // whose billing country is unset.
        $this->assertEquals(1, substr_count($xml, '<CodigoPais>'));
        $this->assertStringContainsString('<IDType>07</IDType>', $xml);
    }

    /** @test */
    public function customer_with_neither_spanish_nif_nor_foreign_id_omits_destinatarios(): void
    {
        $customer = Customer::factory()->withoutTaxId()->create();
        $invoice  = $this->issuedInvoice(['invoice_type' => Invoice::TYPE_F2], $customer);
        $record   = $this->chainService()->recordAlta($invoice, $this->company);

        $xml = $this->builder()->buildAlta($record);

        $this->validator()->validate($xml);
        $this->assertStringNotContainsString('<Destinatarios>', $xml);
        // Exactly one IDOtro in the whole document - the software
        // producer's own (config, Morocco) - none for a destinatario,
        // since there is none.
        $this->assertEquals(1, substr_count($xml, '<IDOtro>'));
    }

    // ── Software producer identity ─────────────────────────────────

    /** @test */
    public function software_producer_uses_idotro_when_no_spanish_nif_is_configured(): void
    {
        // setUp() already configures the producer via the Morocco/IDOtro
        // branch (no VERIFACTU_PRODUCER_NIF) - this test names that
        // explicitly, per RD 1007/2023 art. 13 / Orden HAC/1177/2024 art.
        // 15.1.i, which explicitly allows a non-Spanish producer.
        $invoice = $this->issuedInvoice();
        $record  = $this->chainService()->recordAlta($invoice, $this->company);

        $xml = $this->builder()->buildAlta($record);
        $this->validator()->validate($xml);

        // The SistemaInformatico block's own IDOtro, not the destinatario's.
        $this->assertMatchesRegularExpression(
            '/<SistemaInformatico>.*<IDOtro>\s*<CodigoPais>MA<\/CodigoPais>\s*<IDType>06<\/IDType>\s*<ID>RC-TEST-12345<\/ID>\s*<\/IDOtro>/s',
            $xml
        );
    }

    // ── Tax regime / operation qualification reconciliation ──────────

    /** @test */
    public function every_desglose_row_uses_general_regime_and_subject_not_exempt(): void
    {
        $invoice = $this->issuedInvoice();
        $record  = $this->chainService()->recordAlta($invoice, $this->company);

        $xml = $this->builder()->buildAlta($record);
        $this->validator()->validate($xml);

        $this->assertStringContainsString('<ClaveRegimen>01</ClaveRegimen>', $xml);
        $this->assertStringContainsString('<CalificacionOperacion>S1</CalificacionOperacion>', $xml);
        $this->assertStringNotContainsString('<OperacionExenta>', $xml);
    }

    /** @test */
    public function an_invoice_with_unaccounted_taxable_base_is_rejected_rather_than_silently_submitted(): void
    {
        // total - vta implies more base than vta4+vta10+vta21 can explain
        // (e.g. an unsupported 0%/exempt line) - must never be silently
        // dropped from Desglose.
        $invoice = $this->issuedInvoice([
            'sub_total' => 100.00,
            'vta21'     => 21.00,
            'vta'       => 21.00,
            'total'     => 171.00, // implies 150 of base, only 100 accounted for
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no coincide con Importe Total - Cuota Total');

        $this->chainService()->recordAlta($invoice, $this->company);
    }

    // ── Snapshot immutability (item 5 / item 8) ──────────────────────

    /** @test */
    public function xml_is_unchanged_after_the_source_invoice_customer_and_company_are_mutated(): void
    {
        $original = $this->issuedInvoice();
        $this->chainService()->recordAlta($original, $this->company);

        $rectificationService = new InvoiceRectificationService(app(InvoiceNumberingService::class));
        $rectification = $rectificationService->createRectification(
            $original,
            InvoiceRectificationService::REASON_ERROR_DATOS,
            Invoice::RECTIFICATION_MODE_SUSTITUCION
        );
        $rectification->descripcion_operacion = 'Sustitución completa de la factura ' . $original->reference;
        $rectification->save();
        app(InvoiceNumberingService::class)->assignRectificationNumber($rectification, $this->company);
        $rectification->status    = Invoice::STATUS_ISSUED;
        $rectification->issued_at = now();
        $rectification->save();

        $record  = $this->chainService()->recordAlta($rectification, $this->company);
        $xmlBefore = $this->builder()->buildAlta($record->fresh(['taxDetails', 'previousRecord']));

        // Mutate every mutable source this record's XML could theoretically
        // have depended on - including fields Phase 2A normally locks,
        // bypassed here at the DB layer specifically to prove the XML
        // builder never re-reads them, not to test the app-level guards
        // (already covered by InvoiceLifecycleTest).
        $this->company->legal_name = 'Nombre Cambiado Después De Facturar SL';
        $this->company->verifactu_installation_number = 'INSTALL-CAMBIADO';
        $this->company->save();

        \Illuminate\Support\Facades\DB::table('customers')
            ->where('id', $original->customer_id)
            ->update(['company_name' => 'Nombre De Cliente Cambiado', 'tax_id' => 'X99999999']);

        \Illuminate\Support\Facades\DB::table('invoices')
            ->where('id', $original->id)
            ->update(['reference' => 'REF-CAMBIADA-999', 'date' => now()->addYear()->toDateString()]);

        $xmlAfter = $this->builder()->buildAlta($record->fresh(['taxDetails', 'previousRecord']));

        $this->assertEquals($xmlBefore, $xmlAfter, 'VerifactuXmlBuilder must never re-read mutable Invoice/Customer/CompanyProfile data.');
        $this->validator()->validate($xmlAfter);
        $this->assertStringNotContainsString('Cambiado', $xmlAfter);
        $this->assertStringNotContainsString('REF-CAMBIADA-999', $xmlAfter);
    }

    // ── Tenant isolation ────────────────────────────────────────

    /** @test */
    public function xml_generated_in_one_tenant_never_contains_another_tenants_data(): void
    {
        $invoiceA = $this->issuedInvoice();
        $recordA  = $this->chainService()->recordAlta($invoiceA, $this->company);
        $xmlA     = $this->builder()->buildAlta($recordA);

        $tenantB = Tenant::create(['id' => 'test-verifactu-xml-b-' . uniqid()]);
        $tenantB->domains()->create(['domain' => 'test-verifactu-xml-b-' . uniqid() . '.fakturalista.test']);

        $xmlB = $tenantB->run(function () {
            config(['verifactu.producer.name' => 'Fakturalista Test Producer']);

            $companyB = CompanyProfile::create([
                'legal_name'                    => 'Otra Empresa Distinta SL',
                'tax_id'                        => '11111111H',
                'invoice_prefix'                => 'INV',
                'onboarding_completed_at'       => now(),
                'verifactu_installation_number' => 'TEST-INSTALL-2',
            ]);
            $customer = Customer::factory()->create(['company_name' => 'Cliente de Otro Tenant', 'tax_id' => 'C22222222']);
            $invoice  = Invoice::create([
                'uuid'                  => Str::uuid()->toString(),
                'reference'             => 'DRAFT-B',
                'customer_id'           => $customer->id,
                'date'                  => now()->toDateString(),
                'expiration_date'       => now()->addDays(30)->toDateString(),
                'status'                => Invoice::STATUS_DRAFT,
                'sub_total'             => 50.00,
                'total'                 => 55.00,
                'vta'                   => 5.00,
                'vta4'                  => 0,
                'vta10'                 => 5.00,
                'vta21'                 => 0,
                'discount_rate'         => 0,
                'discount_amount'       => 0,
                'descripcion_operacion' => 'Servicios distintos del tenant B',
            ]);
            app(InvoiceNumberingService::class)->assignLegalNumber($invoice, $companyB);
            $invoice->status    = Invoice::STATUS_ISSUED;
            $invoice->issued_at = now();
            $invoice->save();

            $recordB = app(VerifactuChainService::class)->recordAlta($invoice, $companyB);

            return app(VerifactuXmlBuilder::class)->buildAlta($recordB);
        });

        $this->validator()->validate($xmlA);
        $this->validator()->validate($xmlB);

        $this->assertStringContainsString('89890001K', $xmlA);
        $this->assertStringNotContainsString('89890001K', $xmlB);
        $this->assertStringContainsString('11111111H', $xmlB);
        $this->assertStringNotContainsString('11111111H', $xmlA);
        $this->assertStringNotContainsString('Otra Empresa Distinta SL', $xmlA);

        $tenantB->delete();
    }
}
