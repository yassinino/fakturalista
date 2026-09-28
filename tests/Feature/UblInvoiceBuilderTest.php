<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceTaxLine;
use App\Models\Tenant;
use App\Models\User;
use App\Services\EInvoicing\InvoiceMapper;
use App\Services\EInvoicing\Ubl\UblInvoiceBuilder;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * E-invoicing Step 2 - generic UBL 2.1 XML generation
 * (App\Services\EInvoicing\Ubl\UblInvoiceBuilder). Verifies the XML is
 * well-formed and asserts specific node values/paths against Fakturalista's
 * own already-calculated invoice data - not UBL XSD schema validation
 * (Step 3) and not any Morocco DGI rule (none exist).
 *
 * Setup mirrors tests/Feature/EInvoiceMapperTest.php (same
 * makeTenant()/tenant->run() pattern), since UBL building depends on the
 * same tenant-scoped company_snapshot/customer_snapshot behavior the
 * mapper already covers.
 */
class UblInvoiceBuilderTest extends TestCase
{
    private const INV_NS = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';
    private const CAC_NS = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    private const CBC_NS = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    private function makeTenant(string $countryCode, array $companyOverrides = []): array
    {
        $id     = 'test-ubl-' . uniqid();
        $tenant = Tenant::create(['id' => $id]);
        $domain = $id . '.fakturalista.test';
        $tenant->domains()->create(['domain' => $domain]);

        tenancy()->initialize($tenant);
        $user = User::factory()->create();
        $company = CompanyProfile::create(array_merge([
            'legal_name'              => 'Test Co',
            'country_code'            => $countryCode,
            'currency'                => $countryCode === 'MA' ? 'MAD' : 'EUR',
            'tax_id'                  => $countryCode === 'ES' ? 'B12345678' : null,
            'invoice_prefix'          => 'INV',
            'onboarding_completed_at' => now(),
        ], $companyOverrides));
        tenancy()->end();

        return [$tenant, $user, $domain, $company];
    }

    private function apiUrl(string $domain, string $path): string
    {
        return 'http://' . $domain . $path;
    }

    /**
     * Same two-line shape as EInvoiceMapperTest: one taxable line at 21%,
     * one taxable line at 0% (a real zero-rated line, not an exemption).
     */
    private function makeDraftInvoiceWithLines(array $customerOverrides = [], array $cartOverrides = []): Invoice
    {
        $customer = Customer::factory()->create(array_merge(['type' => 1], $customerOverrides));

        $invoice = Invoice::create([
            'uuid'            => Str::uuid()->toString(),
            'reference'       => 'INV-UBL-' . uniqid(),
            'customer_id'     => $customer->id,
            'date'            => now()->toDateString(),
            'expiration_date' => now()->addDays(30)->toDateString(),
            'status'          => Invoice::STATUS_DRAFT,
            'sub_total'       => 190.00,
            'discount_rate'   => 0,
            'discount_amount' => 0,
            'vta'             => 21.00,
            'vta4' => 0, 'vta10' => 0, 'vta21' => 21.00,
            'total'           => 211.00,
        ]);

        Cart::create(array_merge([
            'cartable_type' => Invoice::class, 'cartable_id' => $invoice->id,
            'description' => 'Consulting hours', 'qty' => 2, 'unite' => 'hour',
            'price' => 90, 'discount' => 0, 'vta' => 21, 'total' => 180,
            'tax_treatment' => 'taxable',
        ], $cartOverrides));
        Cart::create([
            'cartable_type' => Invoice::class, 'cartable_id' => $invoice->id,
            'description' => 'Zero-rated item', 'qty' => 1, 'unite' => 'piece',
            'price' => 10, 'discount' => 0, 'vta' => 0, 'total' => 10,
            'tax_treatment' => 'taxable',
        ]);

        return $invoice;
    }

    private function buildXml(Invoice $invoice): string
    {
        $dto = app(InvoiceMapper::class)->map($invoice);

        return (new UblInvoiceBuilder())->build($dto);
    }

    /**
     * Loads $xml and asserts it is well-formed, then returns an XPath
     * evaluator with the standard UBL 2.1 prefixes registered.
     */
    private function xpath(string $xml): DOMXPath
    {
        $dom    = new DOMDocument();
        $loaded = $dom->loadXML($xml);
        $this->assertNotFalse($loaded, 'Generated UBL XML must be well-formed.');

        $xp = new DOMXPath($dom);
        $xp->registerNamespace('inv', self::INV_NS);
        $xp->registerNamespace('cac', self::CAC_NS);
        $xp->registerNamespace('cbc', self::CBC_NS);

        return $xp;
    }

    private function xval(DOMXPath $xp, string $query): ?string
    {
        $nodes = $xp->query($query);
        if ($nodes === false || $nodes->length === 0) {
            return null;
        }

        return $nodes->item(0)->textContent;
    }

    /** @test */
    public function it_builds_a_well_formed_ubl_invoice_for_a_standard_mad_invoice(): void
    {
        [$tenant] = $this->makeTenant('MA', ['legal_name' => 'Fakturalista Demo SARL', 'ice' => '001234567000089']);

        $xml = null;
        $tenant->run(function () use (&$xml) {
            $invoice = $this->makeDraftInvoiceWithLines(['company_name' => 'Client SARL']);
            $xml     = $this->buildXml($invoice);
        });

        $xp = $this->xpath($xml);

        $this->assertSame('2.1', $this->xval($xp, '/inv:Invoice/cbc:UBLVersionID'));
        $this->assertNotEmpty($this->xval($xp, '/inv:Invoice/cbc:ID'));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $this->xval($xp, '/inv:Invoice/cbc:IssueDate'));
        $this->assertSame('380', $this->xval($xp, '/inv:Invoice/cbc:InvoiceTypeCode'));
        $this->assertSame('MAD', $this->xval($xp, '/inv:Invoice/cbc:DocumentCurrencyCode'));

        $this->assertSame('Fakturalista Demo SARL', $this->xval(
            $xp,
            '/inv:Invoice/cac:AccountingSupplierParty/cac:Party/cac:PartyName/cbc:Name'
        ));
        $this->assertSame('MA', $this->xval(
            $xp,
            '/inv:Invoice/cac:AccountingSupplierParty/cac:Party/cac:PostalAddress/cac:Country/cbc:IdentificationCode'
        ));
        $this->assertSame('001234567000089', $this->xval(
            $xp,
            '/inv:Invoice/cac:AccountingSupplierParty/cac:Party/cac:PartyTaxScheme/cbc:CompanyID'
        ));
        $this->assertSame('Client SARL', $this->xval(
            $xp,
            '/inv:Invoice/cac:AccountingCustomerParty/cac:Party/cac:PartyName/cbc:Name'
        ));

        $this->assertSame('190.00', $this->xval($xp, '/inv:Invoice/cac:LegalMonetaryTotal/cbc:LineExtensionAmount'));
        $this->assertSame('190.00', $this->xval($xp, '/inv:Invoice/cac:LegalMonetaryTotal/cbc:TaxExclusiveAmount'));
        $this->assertSame('211.00', $this->xval($xp, '/inv:Invoice/cac:LegalMonetaryTotal/cbc:TaxInclusiveAmount'));
        $this->assertSame('211.00', $this->xval($xp, '/inv:Invoice/cac:LegalMonetaryTotal/cbc:PayableAmount'));

        $this->assertSame('21.00', $this->xval($xp, '/inv:Invoice/cac:TaxTotal/cbc:TaxAmount'));
        $this->assertCount(1, $xp->query('/inv:Invoice/cac:TaxTotal/cac:TaxSubtotal'));

        $this->assertCount(2, $xp->query('/inv:Invoice/cac:InvoiceLine'));

        $tenant->delete();
    }

    /** @test */
    public function a_legacy_tax_breakdown_row_omits_taxable_amount_instead_of_inventing_it(): void
    {
        [$tenant] = $this->makeTenant('MA');

        $xml = null;
        $tenant->run(function () use (&$xml) {
            // No InvoiceTaxLine rows exist - InvoiceMapper falls back to
            // the legacy vta21 column, which never stored a taxable base.
            $invoice = $this->makeDraftInvoiceWithLines();
            $xml     = $this->buildXml($invoice);
        });

        $xp = $this->xpath($xml);

        $this->assertNull($this->xval($xp, '/inv:Invoice/cac:TaxTotal/cac:TaxSubtotal/cbc:TaxableAmount'));
        $this->assertSame('21.00', $this->xval($xp, '/inv:Invoice/cac:TaxTotal/cac:TaxSubtotal/cbc:TaxAmount'));
        $this->assertSame('21.00', $this->xval($xp, '/inv:Invoice/cac:TaxTotal/cac:TaxSubtotal/cac:TaxCategory/cbc:Percent'));

        $tenant->delete();
    }

    /** @test */
    public function it_emits_one_invoice_line_per_cart_line_in_order(): void
    {
        [$tenant] = $this->makeTenant('MA');

        $xml = null;
        $tenant->run(function () use (&$xml) {
            $invoice = $this->makeDraftInvoiceWithLines();
            Cart::create([
                'cartable_type' => Invoice::class, 'cartable_id' => $invoice->id,
                'description' => 'Third line', 'qty' => 3, 'unite' => 'day',
                'price' => 50, 'discount' => 0, 'vta' => 21, 'total' => 150,
                'tax_treatment' => 'taxable',
            ]);
            $xml = $this->buildXml($invoice->fresh());
        });

        $xp = $this->xpath($xml);
        $lines = $xp->query('/inv:Invoice/cac:InvoiceLine');
        $this->assertCount(3, $lines);

        $ids = [];
        $descriptions = [];
        foreach ($lines as $line) {
            $ids[]          = $xp->evaluate('string(cbc:ID)', $line);
            $descriptions[] = $xp->evaluate('string(cac:Item/cbc:Description)', $line);
        }

        $this->assertSame(['1', '2', '3'], $ids);
        $this->assertSame(
            ['Consulting hours', 'Zero-rated item', 'Third line'],
            $descriptions
        );

        $tenant->delete();
    }

    /** @test */
    public function it_emits_a_tax_subtotal_per_distinct_rate_when_multiple_rates_are_present(): void
    {
        [$tenant] = $this->makeTenant('MA');

        $xml = null;
        $tenant->run(function () use (&$xml) {
            $invoice = $this->makeDraftInvoiceWithLines();
            InvoiceTaxLine::create([
                'invoice_id'   => $invoice->id,
                'rate'         => 10,
                'treatment'    => 'taxable',
                'taxable_base' => 100.00,
                'tax_amount'   => 10.00,
            ]);
            InvoiceTaxLine::create([
                'invoice_id'   => $invoice->id,
                'rate'         => 21,
                'treatment'    => 'taxable',
                'taxable_base' => 90.00,
                'tax_amount'   => 18.90,
            ]);
            $xml = $this->buildXml($invoice->fresh());
        });

        $xp = $this->xpath($xml);
        $subtotals = $xp->query('/inv:Invoice/cac:TaxTotal/cac:TaxSubtotal');
        $this->assertCount(2, $subtotals);

        $rates = [];
        $bases = [];
        foreach ($subtotals as $subtotal) {
            $rates[] = $xp->evaluate('string(cac:TaxCategory/cbc:Percent)', $subtotal);
            $bases[] = $xp->evaluate('string(cbc:TaxableAmount)', $subtotal);
        }

        $this->assertSame(['10.00', '21.00'], $rates);
        $this->assertSame(['100.00', '90.00'], $bases);

        $tenant->delete();
    }

    /** @test */
    public function a_discounted_line_reports_its_net_amount_and_contributes_to_allowance_total(): void
    {
        [$tenant] = $this->makeTenant('MA');

        $xml = null;
        $tenant->run(function () use (&$xml) {
            $invoice = $this->makeDraftInvoiceWithLines([], [
                'description' => 'Discounted consulting', 'qty' => 2, 'price' => 100,
                'discount' => 10, 'vta' => 21, 'total' => 180,
            ]);
            $xml = $this->buildXml($invoice);
        });

        $xp = $this->xpath($xml);

        // gross 200, 10% discount = 20, taxable base 180.
        $firstLineExtension = $xp->evaluate(
            'string(/inv:Invoice/cac:InvoiceLine[1]/cbc:LineExtensionAmount)'
        );
        $this->assertSame('180.00', $firstLineExtension);

        $allowanceTotal = $this->xval($xp, '/inv:Invoice/cac:LegalMonetaryTotal/cbc:AllowanceTotalAmount');
        $this->assertSame('20.00', $allowanceTotal);

        $tenant->delete();
    }

    /** @test */
    public function a_taxable_zero_rate_line_is_classified_as_zero_rated_not_exempt(): void
    {
        [$tenant] = $this->makeTenant('MA');

        $xml = null;
        $tenant->run(function () use (&$xml) {
            $xml = $this->buildXml($this->makeDraftInvoiceWithLines());
        });

        $xp = $this->xpath($xml);

        $category = $xp->evaluate(
            'string(/inv:Invoice/cac:InvoiceLine[2]/cac:Item/cac:ClassifiedTaxCategory/cbc:ID)'
        );
        $percent = $xp->evaluate(
            'string(/inv:Invoice/cac:InvoiceLine[2]/cac:Item/cac:ClassifiedTaxCategory/cbc:Percent)'
        );

        $this->assertSame('Z', $category);
        $this->assertSame('0.00', $percent);

        $tenant->delete();
    }

    /** @test */
    public function special_xml_characters_in_names_and_descriptions_round_trip_exactly(): void
    {
        [$tenant] = $this->makeTenant('MA', ['legal_name' => 'Seller & Co <SARL> "Officiel"']);

        $xml = null;
        $tenant->run(function () use (&$xml) {
            $invoice = $this->makeDraftInvoiceWithLines(
                ['company_name' => 'Client "A" & <B> \'X\''],
                ['description' => 'Item <spec> & "extras" \'v2\'']
            );
            $xml = $this->buildXml($invoice);
        });

        $xp = $this->xpath($xml);

        $this->assertSame('Seller & Co <SARL> "Officiel"', $this->xval(
            $xp,
            '/inv:Invoice/cac:AccountingSupplierParty/cac:Party/cac:PartyName/cbc:Name'
        ));
        $this->assertSame('Client "A" & <B> \'X\'', $this->xval(
            $xp,
            '/inv:Invoice/cac:AccountingCustomerParty/cac:Party/cac:PartyName/cbc:Name'
        ));
        $this->assertSame('Item <spec> & "extras" \'v2\'', $xp->evaluate(
            'string(/inv:Invoice/cac:InvoiceLine[1]/cac:Item/cbc:Description)'
        ));

        $tenant->delete();
    }

    /** @test */
    public function missing_optional_customer_data_is_omitted_without_error(): void
    {
        [$tenant] = $this->makeTenant('MA');

        $xml = null;
        $tenant->run(function () use (&$xml) {
            $invoice = $this->makeDraftInvoiceWithLines([
                'tax_id' => null, 'vat_number' => null, 'foreign_tax_id' => null,
                'address_billing' => null, 'city_billing' => null,
                'post_code_billing' => null, 'billing_country_id' => null,
            ]);
            $xml = $this->buildXml($invoice);
        });

        $xp = $this->xpath($xml);

        $this->assertCount(0, $xp->query(
            '/inv:Invoice/cac:AccountingCustomerParty/cac:Party/cac:PostalAddress'
        ));
        $this->assertCount(0, $xp->query(
            '/inv:Invoice/cac:AccountingCustomerParty/cac:Party/cac:PartyTaxScheme'
        ));
        // PartyLegalEntity/RegistrationName is always present (it is the
        // party's own name, not optional identity data).
        $this->assertNotEmpty($this->xval(
            $xp,
            '/inv:Invoice/cac:AccountingCustomerParty/cac:Party/cac:PartyLegalEntity/cbc:RegistrationName'
        ));

        $tenant->delete();
    }

    /** @test */
    public function an_unrecognised_unit_label_falls_back_to_the_configured_default_code(): void
    {
        [$tenant] = $this->makeTenant('MA');

        $xml = null;
        $tenant->run(function () use (&$xml) {
            $invoice = $this->makeDraftInvoiceWithLines([], ['unite' => 'boxes']);
            $xml     = $this->buildXml($invoice);
        });

        $xp = $this->xpath($xml);
        $unitCode = $xp->evaluate(
            'string(/inv:Invoice/cac:InvoiceLine[1]/cbc:InvoicedQuantity/@unitCode)'
        );
        $this->assertSame('C62', $unitCode);

        config(['einvoicing.default_unit_code' => 'XBX']);
        $xml2 = null;
        $tenant->run(function () use (&$xml2) {
            $invoice = $this->makeDraftInvoiceWithLines([], ['unite' => 'boxes']);
            $xml2    = $this->buildXml($invoice);
        });
        $xp2 = $this->xpath($xml2);
        $this->assertSame('XBX', $xp2->evaluate(
            'string(/inv:Invoice/cac:InvoiceLine[1]/cbc:InvoicedQuantity/@unitCode)'
        ));

        $tenant->delete();
    }

    /** @test */
    public function a_recognised_unit_label_maps_to_its_un_cefact_code(): void
    {
        [$tenant] = $this->makeTenant('MA');

        $xml = null;
        $tenant->run(function () use (&$xml) {
            $xml = $this->buildXml($this->makeDraftInvoiceWithLines());
        });

        $xp = $this->xpath($xml);
        $this->assertSame('HUR', $xp->evaluate(
            'string(/inv:Invoice/cac:InvoiceLine[1]/cbc:InvoicedQuantity/@unitCode)'
        ));
        $this->assertSame('C62', $xp->evaluate(
            'string(/inv:Invoice/cac:InvoiceLine[2]/cbc:InvoicedQuantity/@unitCode)'
        ));

        $tenant->delete();
    }

    /** @test */
    public function an_issued_invoices_xml_keeps_the_snapshotted_identity_after_a_later_rename(): void
    {
        [$tenant, $user, $domain] = $this->makeTenant('MA', ['legal_name' => 'Original Name SARL', 'ice' => 'ICE-ORIGINAL']);

        $invoice = null;
        $tenant->run(function () use (&$invoice) {
            $invoice = $this->makeDraftInvoiceWithLines();
        });

        $this->actingAs($user, 'api');
        $this->postJson($this->apiUrl($domain, '/api/invoices/' . $invoice->uuid . '/issue'))->assertStatus(200);

        $tenant->run(function () {
            CompanyProfile::first()->update(['legal_name' => 'Renamed Later SARL', 'ice' => 'ICE-CHANGED']);
        });

        $xml = null;
        $tenant->run(function () use ($invoice, &$xml) {
            $xml = $this->buildXml($invoice->fresh());
        });

        $xp = $this->xpath($xml);
        $this->assertSame('Original Name SARL', $this->xval(
            $xp,
            '/inv:Invoice/cac:AccountingSupplierParty/cac:Party/cac:PartyName/cbc:Name'
        ));
        $this->assertSame('ICE-ORIGINAL', $this->xval(
            $xp,
            '/inv:Invoice/cac:AccountingSupplierParty/cac:Party/cac:PartyTaxScheme/cbc:CompanyID'
        ));

        // An issued (non-rectification) invoice still gets the generic
        // "commercial invoice" type code - the rename doesn't affect this.
        $this->assertSame('380', $this->xval($xp, '/inv:Invoice/cbc:InvoiceTypeCode'));

        $tenant->delete();
    }
}
