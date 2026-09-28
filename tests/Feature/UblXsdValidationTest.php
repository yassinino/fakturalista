<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use App\Services\EInvoicing\InvoiceMapper;
use App\Services\EInvoicing\Ubl\UblInvoiceBuilder;
use App\Services\EInvoicing\Ubl\UblValidationError;
use App\Services\EInvoicing\Ubl\UblValidationResult;
use App\Services\EInvoicing\Ubl\UblValidator;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * E-invoicing Step 3 - real UBL 2.1 XSD schema validation
 * (App\Services\EInvoicing\Ubl\UblValidator::validateXsd()), checked
 * against the official OASIS schemas bundled under
 * resources/einvoicing/ubl/2.1/xsd/. Not Schematron/business-rule
 * validation (a later step) and not any Morocco DGI rule (none exist).
 *
 * Setup mirrors tests/Feature/UblInvoiceBuilderTest.php.
 */
class UblXsdValidationTest extends TestCase
{
    private const INV_NS = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';

    private function makeTenant(string $countryCode, array $companyOverrides = []): array
    {
        $id     = 'test-xsd-' . uniqid();
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

    private function makeDraftInvoiceWithLines(array $customerOverrides = []): Invoice
    {
        $customer = Customer::factory()->create(array_merge(['type' => 1], $customerOverrides));

        $invoice = Invoice::create([
            'uuid'            => Str::uuid()->toString(),
            'reference'       => 'INV-XSD-' . uniqid(),
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

        Cart::create([
            'cartable_type' => Invoice::class, 'cartable_id' => $invoice->id,
            'description' => 'Consulting hours', 'qty' => 2, 'unite' => 'hour',
            'price' => 90, 'discount' => 0, 'vta' => 21, 'total' => 180,
            'tax_treatment' => 'taxable',
        ]);
        Cart::create([
            'cartable_type' => Invoice::class, 'cartable_id' => $invoice->id,
            'description' => 'Zero-rated item', 'qty' => 1, 'unite' => 'piece',
            'price' => 10, 'discount' => 0, 'vta' => 0, 'total' => 10,
            'tax_treatment' => 'taxable',
        ]);

        return $invoice;
    }

    private function validXml(): string
    {
        $dto = app(InvoiceMapper::class)->map($this->makeDraftInvoiceWithLines());

        return (new UblInvoiceBuilder())->build($dto);
    }

    /**
     * Loads $xml, applies $mutator($dom, $xp) to it, and returns the
     * mutated XML - used to turn a valid, builder-generated document into
     * a deliberately schema-invalid one without hand-writing fragile XML
     * strings.
     */
    private function mutate(string $xml, callable $mutator): string
    {
        $dom = new DOMDocument();
        $dom->loadXML($xml);
        $xp = new DOMXPath($dom);
        $xp->registerNamespace('inv', self::INV_NS);

        $mutator($dom, $xp);

        return $dom->saveXML();
    }

    /** @test */
    public function a_valid_fakturalista_generated_invoice_passes_the_official_ubl_21_xsd(): void
    {
        [$tenant] = $this->makeTenant('MA', ['legal_name' => 'Fakturalista Demo SARL']);

        $result = null;
        $tenant->run(function () use (&$result) {
            $xml    = $this->validXml();
            $result = (new UblValidator())->validateXsd($xml);
        });

        $this->assertInstanceOf(UblValidationResult::class, $result);
        $this->assertTrue($result->valid);
        $this->assertSame([], $result->errors);

        $tenant->delete();
    }

    /** @test */
    public function malformed_not_well_formed_xml_fails_validation(): void
    {
        $result = (new UblValidator())->validateXsd('<Invoice><cbc:ID>1</cbc:ID>');

        $this->assertFalse($result->valid);
        $this->assertNotEmpty($result->errors);
        $this->assertInstanceOf(UblValidationError::class, $result->errors[0]);
    }

    /** @test */
    public function well_formed_xml_missing_the_required_invoice_id_fails_schema_validation(): void
    {
        [$tenant] = $this->makeTenant('MA');

        $result = null;
        $tenant->run(function () use (&$result) {
            $xml = $this->validXml();

            $mutated = $this->mutate($xml, function (DOMDocument $dom, DOMXPath $xp) {
                $id = $xp->query('/inv:Invoice/*[local-name()="ID"]')->item(0);
                $id->parentNode->removeChild($id);
            });

            $result = (new UblValidator())->validateXsd($mutated);
        });

        $this->assertFalse($result->valid);
        $this->assertNotEmpty($result->errors);

        $tenant->delete();
    }

    /** @test */
    public function well_formed_xml_missing_the_required_issue_date_fails_schema_validation(): void
    {
        [$tenant] = $this->makeTenant('MA');

        $result = null;
        $tenant->run(function () use (&$result) {
            $xml = $this->validXml();

            $mutated = $this->mutate($xml, function (DOMDocument $dom, DOMXPath $xp) {
                $issueDate = $xp->query('/inv:Invoice/*[local-name()="IssueDate"]')->item(0);
                $issueDate->parentNode->removeChild($issueDate);
            });

            $result = (new UblValidator())->validateXsd($mutated);
        });

        $this->assertFalse($result->valid);
        $this->assertNotEmpty($result->errors);

        $tenant->delete();
    }

    /** @test */
    public function well_formed_xml_with_elements_out_of_sequence_order_fails_schema_validation(): void
    {
        [$tenant] = $this->makeTenant('MA');

        $result = null;
        $tenant->run(function () use (&$result) {
            $xml = $this->validXml();

            // The UBL InvoiceType is an xsd:sequence - ID must precede
            // IssueDate. Moving IssueDate before ID violates that order
            // even though every required element is still present.
            $mutated = $this->mutate($xml, function (DOMDocument $dom, DOMXPath $xp) {
                $root      = $dom->documentElement;
                $id        = $xp->query('/inv:Invoice/*[local-name()="ID"]')->item(0);
                $issueDate = $xp->query('/inv:Invoice/*[local-name()="IssueDate"]')->item(0);
                $root->insertBefore($issueDate, $id);
            });

            $result = (new UblValidator())->validateXsd($mutated);
        });

        $this->assertFalse($result->valid);
        $this->assertNotEmpty($result->errors);

        $tenant->delete();
    }

    /** @test */
    public function well_formed_xml_with_an_element_the_schema_does_not_allow_fails_validation(): void
    {
        [$tenant] = $this->makeTenant('MA');

        $result = null;
        $tenant->run(function () use (&$result) {
            $xml = $this->validXml();

            $mutated = $this->mutate($xml, function (DOMDocument $dom, DOMXPath $xp) {
                $root  = $dom->documentElement;
                $bogus = $dom->createElementNS(
                    'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                    'cbc:ThisElementDoesNotExistInUbl',
                    'x'
                );
                $root->appendChild($bogus);
            });

            $result = (new UblValidator())->validateXsd($mutated);
        });

        $this->assertFalse($result->valid);
        $this->assertNotEmpty($result->errors);

        $tenant->delete();
    }

    /** @test */
    public function validation_errors_expose_useful_structured_information(): void
    {
        [$tenant] = $this->makeTenant('MA');

        $result = null;
        $tenant->run(function () use (&$result) {
            $xml = $this->validXml();

            $mutated = $this->mutate($xml, function (DOMDocument $dom, DOMXPath $xp) {
                $id = $xp->query('/inv:Invoice/*[local-name()="ID"]')->item(0);
                $id->parentNode->removeChild($id);
            });

            $result = (new UblValidator())->validateXsd($mutated);
        });

        $this->assertFalse($result->valid);
        $this->assertNotEmpty($result->errors);

        $error = $result->errors[0];
        $this->assertInstanceOf(UblValidationError::class, $error);
        $this->assertNotEmpty($error->message);
        $this->assertIsInt($error->line);

        $array = $error->toArray();
        $this->assertArrayHasKey('message', $array);
        $this->assertArrayHasKey('line', $array);
        $this->assertArrayHasKey('column', $array);
        $this->assertArrayHasKey('level', $array);
        $this->assertArrayHasKey('code', $array);

        $resultArray = $result->toArray();
        $this->assertFalse($resultArray['valid']);
        $this->assertNotEmpty($resultArray['errors']);

        $tenant->delete();
    }

    /** @test */
    public function xsd_validation_never_resolves_external_entities(): void
    {
        // A classic XXE payload: if external entities were resolved, this
        // would either fail with a filesystem/network side effect or leak
        // /etc/passwd's content into the parsed ID value. With
        // resolveExternals/substituteEntities both false and LIBXML_NONET
        // set, the parse must simply fail (undefined entity) or succeed
        // with the entity left unresolved - never leak file content.
        $xxe = <<<'XML'
<?xml version="1.0"?>
<!DOCTYPE Invoice [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
         xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
  <cbc:ID>&xxe;</cbc:ID>
</Invoice>
XML;

        $result = (new UblValidator())->validateXsd($xxe);

        $this->assertFalse($result->valid);
        foreach ($result->errors as $error) {
            $this->assertStringNotContainsString('root:', $error->message);
        }
    }

    /** @test */
    public function existing_step_one_and_step_two_mapper_and_builder_behaviour_is_unaffected(): void
    {
        [$tenant] = $this->makeTenant('MA', ['legal_name' => 'Fakturalista Demo SARL']);

        $tenant->run(function () {
            $invoice = $this->makeDraftInvoiceWithLines();
            $dto     = app(InvoiceMapper::class)->map($invoice);

            // The Step 1 structural validator is untouched by Step 3.
            $this->assertSame([], (new UblValidator())->validate($dto));

            // The Step 2 builder still produces XML, and that XML now
            // also passes real XSD validation.
            $xml = (new UblInvoiceBuilder())->build($dto);
            $this->assertTrue((new UblValidator())->validateXsd($xml)->valid);
        });

        $tenant->delete();
    }
}
