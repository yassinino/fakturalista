<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CompanyProfile;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use App\Services\EInvoicing\Ubl\UblValidationError;
use App\Services\EInvoicing\Ubl\UblValidationResult;
use App\Services\EInvoicing\Ubl\UblValidator;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * E-invoicing Step 4 - the real HTTP endpoint
 * (GET /invoices/{invoice}/export/ubl, App\Http\Controllers\InvoiceController::exportUbl())
 * that exposes the Step 1-3 pipeline (InvoiceMapper -> EInvoiceData ->
 * UblInvoiceBuilder -> UblValidator::validateXsd()) as a download. Not
 * PEPPOL/Schematron, not DGI, not signatures/QR - none of that exists.
 *
 * Setup mirrors tests/Feature/EInvoiceMapperTest.php.
 */
class InvoiceUblExportTest extends TestCase
{
    private const INV_NS = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';

    private function makeTenant(string $countryCode, array $companyOverrides = []): array
    {
        $id     = 'test-ublexp-' . uniqid();
        $tenant = Tenant::create(['id' => $id]);
        $domain = $id . '.fakturalista.test';
        $tenant->domains()->create(['domain' => $domain]);

        tenancy()->initialize($tenant);
        $user = User::factory()->create();
        CompanyProfile::create(array_merge([
            'legal_name'              => 'Test Co',
            'country_code'            => $countryCode,
            'currency'                => $countryCode === 'MA' ? 'MAD' : 'EUR',
            'tax_id'                  => $countryCode === 'ES' ? 'B12345678' : null,
            'invoice_prefix'          => 'INV',
            'onboarding_completed_at' => now(),
        ], $companyOverrides));
        tenancy()->end();

        return [$tenant, $user, $domain];
    }

    private function apiUrl(string $domain, string $path): string
    {
        return 'http://' . $domain . $path;
    }

    private function makeDraftInvoiceWithLines(array $customerOverrides = [], array $cartOverrides = []): Invoice
    {
        $customer = Customer::factory()->create(array_merge(['type' => 1], $customerOverrides));

        $invoice = Invoice::create([
            'uuid'            => Str::uuid()->toString(),
            'reference'       => 'INV-UBLEXP-' . uniqid(),
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

    /** @test */
    public function an_authorized_tenant_can_export_its_own_invoice(): void
    {
        [$tenant, $user, $domain] = $this->makeTenant('MA');

        $invoice = null;
        $tenant->run(function () use (&$invoice) {
            $invoice = $this->makeDraftInvoiceWithLines();
        });

        $this->actingAs($user, 'api');
        $response = $this->getJson($this->apiUrl($domain, '/api/invoices/' . $invoice->uuid . '/export/ubl'));

        $response->assertStatus(200);
        $this->assertStringStartsWith('<?xml', $response->getContent());

        $tenant->delete();
    }

    /** @test */
    public function another_tenant_cannot_export_a_different_tenants_invoice(): void
    {
        [$tenantA] = $this->makeTenant('MA', ['legal_name' => 'Tenant A SARL']);
        [$tenantB, $userB, $domainB] = $this->makeTenant('MA', ['legal_name' => 'Tenant B SARL']);

        $invoiceA = null;
        $tenantA->run(function () use (&$invoiceA) {
            $invoiceA = $this->makeDraftInvoiceWithLines();
        });

        // Authenticated, but as tenant B's own user, against tenant B's own
        // domain - tenant resolution is domain-based (stancl/tenancy, one
        // database per tenant), so tenant A's invoice simply does not
        // exist in tenant B's database.
        $this->actingAs($userB, 'api');
        $this->getJson($this->apiUrl($domainB, '/api/invoices/' . $invoiceA->uuid . '/export/ubl'))
            ->assertStatus(404);

        $tenantA->delete();
        $tenantB->delete();
    }

    /** @test */
    public function the_response_has_the_correct_xml_content_type(): void
    {
        [$tenant, $user, $domain] = $this->makeTenant('MA');

        $invoice = null;
        $tenant->run(function () use (&$invoice) {
            $invoice = $this->makeDraftInvoiceWithLines();
        });

        $this->actingAs($user, 'api');
        $response = $this->getJson($this->apiUrl($domain, '/api/invoices/' . $invoice->uuid . '/export/ubl'));

        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $tenant->delete();
    }

    /** @test */
    public function the_download_filename_is_safe_and_based_on_the_invoice_number(): void
    {
        [$tenant, $user, $domain] = $this->makeTenant('MA');

        $invoice = null;
        $tenant->run(function () use (&$invoice) {
            $invoice = $this->makeDraftInvoiceWithLines();
        });

        $this->actingAs($user, 'api');
        $response = $this->getJson($this->apiUrl($domain, '/api/invoices/' . $invoice->uuid . '/export/ubl'));

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertNotNull($disposition);
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertMatchesRegularExpression('/filename="[A-Za-z0-9._-]+\.xml"/', $disposition);
        // No path traversal or filesystem-unsafe characters can leak through.
        $this->assertStringNotContainsString('/', $disposition);
        $this->assertStringNotContainsString('..', $disposition);

        $tenant->delete();
    }

    /** @test */
    public function the_exported_xml_passes_the_official_bundled_ubl_21_xsd(): void
    {
        [$tenant, $user, $domain] = $this->makeTenant('MA', ['legal_name' => 'Fakturalista Demo SARL']);

        $invoice = null;
        $tenant->run(function () use (&$invoice) {
            $invoice = $this->makeDraftInvoiceWithLines();
        });

        $this->actingAs($user, 'api');
        $response = $this->getJson($this->apiUrl($domain, '/api/invoices/' . $invoice->uuid . '/export/ubl'));
        $response->assertStatus(200);

        $result = (new UblValidator())->validateXsd($response->getContent());
        $this->assertTrue($result->valid, 'Exported XML failed the official UBL 2.1 XSD: ' . json_encode(array_map(
            fn (UblValidationError $e) => $e->toArray(),
            $result->errors
        )));

        $tenant->delete();
    }

    /** @test */
    public function a_validation_failure_prevents_the_download_and_returns_a_clean_error(): void
    {
        [$tenant, $user, $domain] = $this->makeTenant('MA');

        $invoice = null;
        $tenant->run(function () use (&$invoice) {
            $invoice = $this->makeDraftInvoiceWithLines();
        });

        // Force the XSD step to fail, without needing a genuinely
        // schema-invalid invoice (Step 3 already proved real invoices
        // validate cleanly) - proves the controller actually gates the
        // download on UblValidator::validateXsd(), not just that it
        // never happens to fail in practice.
        $this->app->bind(UblValidator::class, function () {
            return new class extends UblValidator {
                public function validateXsd(string $xml): UblValidationResult
                {
                    return new UblValidationResult(false, [
                        new UblValidationError('forced failure for testing', 12, 3, 2, 1871),
                    ]);
                }
            };
        });

        $this->actingAs($user, 'api');
        $response = $this->getJson($this->apiUrl($domain, '/api/invoices/' . $invoice->uuid . '/export/ubl'));

        $response->assertStatus(500);
        $response->assertJsonStructure(['message']);
        // Never the raw libxml detail - only Fakturalista's own generic message.
        $this->assertStringNotContainsString('forced failure for testing', $response->getContent());
        $this->assertStringNotContainsString('cvc-', $response->getContent());

        $tenant->delete();
    }

    /** @test */
    public function special_characters_in_invoice_customer_and_company_data_export_correctly(): void
    {
        [$tenant, $user, $domain] = $this->makeTenant('MA', ['legal_name' => 'Seller & Co <SARL> "Officiel"']);

        $invoice = null;
        $tenant->run(function () use (&$invoice) {
            $invoice = $this->makeDraftInvoiceWithLines(
                ['company_name' => 'Client "A" & <B>'],
                ['description' => 'Item <spec> & "extras"']
            );
        });

        $this->actingAs($user, 'api');
        $response = $this->getJson($this->apiUrl($domain, '/api/invoices/' . $invoice->uuid . '/export/ubl'));
        $response->assertStatus(200);

        $dom = new DOMDocument();
        $this->assertTrue($dom->loadXML($response->getContent()));
        $xp = new DOMXPath($dom);
        $xp->registerNamespace('inv', self::INV_NS);
        $xp->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xp->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

        $this->assertSame('Seller & Co <SARL> "Officiel"', $xp->evaluate(
            'string(/inv:Invoice/cac:AccountingSupplierParty/cac:Party/cac:PartyName/cbc:Name)'
        ));
        $this->assertSame('Client "A" & <B>', $xp->evaluate(
            'string(/inv:Invoice/cac:AccountingCustomerParty/cac:Party/cac:PartyName/cbc:Name)'
        ));

        $result = (new UblValidator())->validateXsd($response->getContent());
        $this->assertTrue($result->valid);

        $tenant->delete();
    }

    /** @test */
    public function an_issued_invoices_customer_country_code_is_preserved_in_the_snapshot_and_exported(): void
    {
        [$tenant, $user, $domain] = $this->makeTenant('MA');

        $invoice = null;
        $tenant->run(function () use (&$invoice) {
            $country  = Country::create(['name' => 'France', 'code' => 'FR']);
            $customer = Customer::factory()->create(['type' => 1, 'billing_country_id' => $country->id]);
            $invoice  = $this->makeDraftInvoiceWithLines()->fresh();
            // Reassign to the country-having customer without going
            // through the full create() helper twice.
            Invoice::where('id', $invoice->id)->update(['customer_id' => $customer->id]);
        });

        $this->actingAs($user, 'api');
        $this->postJson($this->apiUrl($domain, '/api/invoices/' . $invoice->uuid . '/issue'))->assertStatus(200);

        $tenant->run(function () use ($invoice) {
            // The snapshot written by issuance must carry the ISO code.
            $snapshot = Invoice::where('uuid', $invoice->uuid)->first()->customer_snapshot;
            $this->assertSame('FR', $snapshot['country_code'] ?? null);
        });

        $response = $this->getJson($this->apiUrl($domain, '/api/invoices/' . $invoice->uuid . '/export/ubl'));
        $response->assertStatus(200);

        $dom = new DOMDocument();
        $dom->loadXML($response->getContent());
        $xp = new DOMXPath($dom);
        $xp->registerNamespace('inv', self::INV_NS);
        $xp->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xp->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

        $this->assertSame('FR', $xp->evaluate(
            'string(/inv:Invoice/cac:AccountingCustomerParty/cac:Party/cac:PostalAddress/cac:Country/cbc:IdentificationCode)'
        ));

        $tenant->delete();
    }

    /** @test */
    public function a_customer_without_a_country_still_exports_without_an_identification_code(): void
    {
        [$tenant, $user, $domain] = $this->makeTenant('MA');

        $invoice = null;
        $tenant->run(function () use (&$invoice) {
            $invoice = $this->makeDraftInvoiceWithLines(['billing_country_id' => null]);
        });

        $this->actingAs($user, 'api');
        $response = $this->getJson($this->apiUrl($domain, '/api/invoices/' . $invoice->uuid . '/export/ubl'));
        $response->assertStatus(200);

        $dom = new DOMDocument();
        $dom->loadXML($response->getContent());
        $xp = new DOMXPath($dom);
        $xp->registerNamespace('inv', self::INV_NS);
        $xp->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xp->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

        $nodes = $xp->query(
            '/inv:Invoice/cac:AccountingCustomerParty/cac:Party/cac:PostalAddress/cac:Country/cbc:IdentificationCode'
        );
        $this->assertSame(0, $nodes->length);

        $tenant->delete();
    }
}
