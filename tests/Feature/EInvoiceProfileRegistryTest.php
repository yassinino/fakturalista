<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CompanyProfile;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Customer;
use App\Services\EInvoicing\Contracts\EInvoiceProfileInterface;
use App\Services\EInvoicing\DTO\EInvoiceData;
use App\Services\EInvoicing\DTO\EInvoiceLineData;
use App\Services\EInvoicing\DTO\EInvoicePartyData;
use App\Services\EInvoicing\DTO\EInvoiceTaxData;
use App\Services\EInvoicing\EInvoiceProfileRegistry;
use App\Services\EInvoicing\Exceptions\UnknownEInvoiceProfileException;
use App\Services\EInvoicing\Profiles\GenericUbl21Profile;
use App\Services\EInvoicing\Ubl\UblValidator;
use App\Services\Tax\TaxTreatment;
use DOMDocument;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * E-invoicing Step 5 - the profile registry/resolver
 * (App\Services\EInvoicing\EInvoiceProfileRegistry) and the generic
 * profile that represents current behaviour
 * (App\Services\EInvoicing\Profiles\GenericUbl21Profile). Most of these
 * tests are pure - they build an EInvoiceData DTO by hand rather than
 * through a real Invoice/tenant, since the registry/profile plumbing
 * itself doesn't depend on Fakturalista's invoice data at all (that
 * mapping is already covered by tests/Feature/EInvoiceMapperTest.php and
 * tests/Feature/UblInvoiceBuilderTest.php - not duplicated here). Only
 * the "existing export endpoint" and "tenant isolation" cases need a
 * real tenant/HTTP request.
 */
class EInvoiceProfileRegistryTest extends TestCase
{
    private function sampleData(): EInvoiceData
    {
        $party = fn (string $name) => new EInvoicePartyData(
            name: $name,
            tradeName: null,
            addressLine1: null,
            addressLine2: null,
            city: null,
            postalCode: null,
            countryCode: 'MA',
            countryName: 'Morocco',
            taxIdentifiers: [],
            email: null,
            phone: null,
        );

        return new EInvoiceData(
            invoiceNumber: 'INV-REGISTRY-TEST-1',
            issueDate: Carbon::now(),
            dueDate: null,
            currency: 'MAD',
            seller: $party('Seller Co'),
            customer: $party('Customer Co'),
            lines: [
                new EInvoiceLineData(
                    description: 'Consulting',
                    quantity: 1.0,
                    unitOfMeasure: 'hour',
                    unitPrice: 100.0,
                    grossAmount: 100.0,
                    discountPercent: 0.0,
                    discountAmount: 0.0,
                    taxableBase: 100.0,
                    taxRate: 20.0,
                    taxTreatment: TaxTreatment::TAXABLE,
                    taxAmount: 20.0,
                    lineTotal: 100.0,
                ),
            ],
            taxBreakdown: [
                new EInvoiceTaxData(rate: 20.0, treatment: TaxTreatment::TAXABLE, taxableBase: 100.0, taxAmount: 20.0),
            ],
            subtotalExcludingTax: 100.0,
            totalTaxAmount: 20.0,
            totalIncludingTax: 120.0,
            amountPayable: 120.0,
            note: null,
            isRectification: false,
        );
    }

    /** @test */
    public function the_generic_ubl_2_1_profile_resolves(): void
    {
        $profile = (new EInvoiceProfileRegistry())->get('ubl_2_1');

        $this->assertInstanceOf(GenericUbl21Profile::class, $profile);
        $this->assertInstanceOf(EInvoiceProfileInterface::class, $profile);
        $this->assertSame('ubl_2_1', $profile->key());
    }

    /** @test */
    public function omitting_the_profile_id_resolves_the_configured_default(): void
    {
        $profile = (new EInvoiceProfileRegistry())->get();

        $this->assertSame('ubl_2_1', $profile->key());
    }

    /** @test */
    public function an_unknown_profile_id_fails_explicitly_instead_of_falling_back(): void
    {
        $this->expectException(UnknownEInvoiceProfileException::class);

        (new EInvoiceProfileRegistry())->get('does-not-exist');
    }

    /** @test */
    public function changing_the_default_profile_to_an_unregistered_one_fails_safely(): void
    {
        config(['einvoicing.default_profile' => 'not-a-registered-profile']);

        $this->expectException(UnknownEInvoiceProfileException::class);

        (new EInvoiceProfileRegistry())->get();
    }

    /** @test */
    public function the_generic_profile_generates_well_formed_xml(): void
    {
        $profile = (new EInvoiceProfileRegistry())->get('ubl_2_1');
        $xml     = $profile->build($this->sampleData());

        $this->assertStringStartsWith('<?xml', $xml);

        $dom = new DOMDocument();
        $this->assertTrue($dom->loadXML($xml));
    }

    /** @test */
    public function the_generated_xml_passes_the_official_bundled_oasis_xsd(): void
    {
        $profile = (new EInvoiceProfileRegistry())->get('ubl_2_1');
        $xml     = $profile->build($this->sampleData());

        $result = $profile->validateOutput($xml);

        $this->assertTrue($result->valid);
        $this->assertSame([], $result->errors);

        // Same outcome via UblValidator directly - proves the profile
        // isn't duplicating validation logic, only exposing it.
        $this->assertTrue((new UblValidator())->validateXsd($xml)->valid);
    }

    // ── HTTP-level: the real export endpoint through the new pipeline ──

    private function makeTenant(string $countryCode): array
    {
        $id     = 'test-profreg-' . uniqid();
        $tenant = Tenant::create(['id' => $id]);
        $domain = $id . '.fakturalista.test';
        $tenant->domains()->create(['domain' => $domain]);

        tenancy()->initialize($tenant);
        $user = User::factory()->create();
        CompanyProfile::create([
            'legal_name'              => 'Registry Test Co',
            'country_code'            => $countryCode,
            'currency'                => $countryCode === 'MA' ? 'MAD' : 'EUR',
            'invoice_prefix'          => 'INV',
            'onboarding_completed_at' => now(),
        ]);
        tenancy()->end();

        return [$tenant, $user, $domain];
    }

    private function apiUrl(string $domain, string $path): string
    {
        return 'http://' . $domain . $path;
    }

    private function makeDraftInvoiceWithLines(): Invoice
    {
        $customer = Customer::factory()->create(['type' => 1]);

        $invoice = Invoice::create([
            'uuid'            => Str::uuid()->toString(),
            'reference'       => 'INV-PROFREG-' . uniqid(),
            'customer_id'     => $customer->id,
            'date'            => now()->toDateString(),
            'expiration_date' => now()->addDays(30)->toDateString(),
            'status'          => Invoice::STATUS_DRAFT,
            'sub_total'       => 100.00,
            'discount_rate'   => 0,
            'discount_amount' => 0,
            'vta'             => 20.00,
            'vta4' => 0, 'vta10' => 0, 'vta21' => 20.00,
            'total'           => 120.00,
        ]);

        Cart::create([
            'cartable_type' => Invoice::class, 'cartable_id' => $invoice->id,
            'description' => 'Consulting', 'qty' => 1, 'unite' => 'hour',
            'price' => 100, 'discount' => 0, 'vta' => 20, 'total' => 100,
            'tax_treatment' => 'taxable',
        ]);

        return $invoice;
    }

    /** @test */
    public function the_existing_export_endpoint_still_works_through_the_profile_registry(): void
    {
        [$tenant, $user, $domain] = $this->makeTenant('MA');

        $invoice = null;
        $tenant->run(function () use (&$invoice) {
            $invoice = $this->makeDraftInvoiceWithLines();
        });

        $this->actingAs($user, 'api');
        $response = $this->getJson($this->apiUrl($domain, '/api/invoices/' . $invoice->uuid . '/export/ubl'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $this->assertTrue((new UblValidator())->validateXsd($response->getContent())->valid);

        $tenant->delete();
    }

    /** @test */
    public function an_unregistered_default_profile_makes_the_export_endpoint_fail_safely_not_crash(): void
    {
        [$tenant, $user, $domain] = $this->makeTenant('MA');

        $invoice = null;
        $tenant->run(function () use (&$invoice) {
            $invoice = $this->makeDraftInvoiceWithLines();
        });

        config(['einvoicing.default_profile' => 'not-a-registered-profile']);

        $this->actingAs($user, 'api');
        $response = $this->getJson($this->apiUrl($domain, '/api/invoices/' . $invoice->uuid . '/export/ubl'));

        // A controlled 500 with Fakturalista's own message, never a raw
        // exception/stack trace and never a silent fallback to ubl_2_1.
        $response->assertStatus(500);
        $response->assertJsonStructure(['message']);
        $this->assertStringNotContainsString('UnknownEInvoiceProfileException', $response->getContent());

        $tenant->delete();
    }

    /** @test */
    public function tenant_isolation_is_unaffected_by_the_profile_registry(): void
    {
        [$tenantA] = $this->makeTenant('MA');
        [$tenantB, $userB, $domainB] = $this->makeTenant('MA');

        $invoiceA = null;
        $tenantA->run(function () use (&$invoiceA) {
            $invoiceA = $this->makeDraftInvoiceWithLines();
        });

        $this->actingAs($userB, 'api');
        $this->getJson($this->apiUrl($domainB, '/api/invoices/' . $invoiceA->uuid . '/export/ubl'))
            ->assertStatus(404);

        $tenantA->delete();
        $tenantB->delete();
    }
}
