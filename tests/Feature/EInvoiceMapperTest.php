<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use App\Services\EInvoicing\DTO\EInvoiceData;
use App\Services\EInvoicing\InvoiceMapper;
use App\Services\EInvoicing\Ubl\UblValidator;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * E-invoicing groundwork, Step 1 (see the Step 1 report). Only verifies
 * that an existing Fakturalista Invoice maps to EInvoiceData correctly
 * and safely - not UBL output (Step 2 doesn't exist yet, see
 * Ubl\UblInvoiceBuilder) and not any Morocco DGI rule (none exist).
 *
 * Setup mirrors tests/Feature/InvoiceIdentitySnapshotTest.php (same
 * makeTenant()/tenant->run() pattern) since this also depends on the
 * same tenant-scoped company_snapshot/customer_snapshot behavior.
 */
class EInvoiceMapperTest extends TestCase
{
    private function makeTenant(string $countryCode, array $companyOverrides = []): array
    {
        $id     = 'test-einv-' . uniqid();
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
     * A draft invoice with two lines - one taxable, one at a 0% rate -
     * so the mapper's line/discount/tax math has something real to map.
     */
    private function makeDraftInvoiceWithLines(array $customerOverrides = []): Invoice
    {
        $customer = Customer::factory()->create(array_merge(['type' => 1], $customerOverrides));

        $invoice = Invoice::create([
            'uuid'            => Str::uuid()->toString(),
            'reference'       => 'INV-EINV-' . uniqid(),
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

    /** @test */
    public function it_maps_a_draft_invoices_core_fields_and_lines(): void
    {
        [$tenant] = $this->makeTenant('MA', ['legal_name' => 'Fakturalista Demo SARL', 'ice' => '001234567000089']);

        $dto = null;
        $tenant->run(function () use (&$dto) {
            $invoice = $this->makeDraftInvoiceWithLines(['company_name' => 'Client SARL', 'ice' => 'CLIENT-ICE-1']);
            $dto = app(InvoiceMapper::class)->map($invoice);
        });

        $this->assertInstanceOf(EInvoiceData::class, $dto);
        $this->assertSame('MAD', $dto->currency);
        $this->assertEquals(190.00, $dto->subtotalExcludingTax);
        $this->assertEquals(21.00, $dto->totalTaxAmount);
        $this->assertEquals(211.00, $dto->totalIncludingTax);
        // No partial-payment tracking exists (see InvoiceMapper's
        // docblock) - amount payable is always the invoice's own total.
        $this->assertEquals($dto->totalIncludingTax, $dto->amountPayable);

        $this->assertCount(2, $dto->lines);
        $this->assertSame('Consulting hours', $dto->lines[0]->description);
        $this->assertEquals(2.0, $dto->lines[0]->quantity);
        $this->assertEquals(90.0, $dto->lines[0]->unitPrice);
        $this->assertSame('hour', $dto->lines[0]->unitOfMeasure);
        $this->assertEquals(21.0, $dto->lines[0]->taxRate);
        $this->assertEquals(180.0, $dto->lines[0]->taxableBase);

        // A draft has no company_snapshot/customer_snapshot yet - the
        // mapper must fall back to the live CompanyProfile/Customer rows.
        $this->assertSame('Fakturalista Demo SARL', $dto->seller->name);
        $this->assertSame('MA', $dto->seller->countryCode);
        $this->assertEquals('001234567000089', $dto->seller->taxIdentifiers['ice']);
        $this->assertSame('Client SARL', $dto->customer->name);
        $this->assertEquals('CLIENT-ICE-1', $dto->customer->taxIdentifiers['ice']);

        $tenant->delete();
    }

    /** @test */
    public function it_maps_issued_invoice_identity_from_the_immutable_snapshot_not_live_data(): void
    {
        [$tenant, $user, $domain] = $this->makeTenant('MA', ['legal_name' => 'Original Name SARL', 'ice' => 'ICE-ORIGINAL']);

        $invoice = null;
        $tenant->run(function () use (&$invoice) {
            $invoice = $this->makeDraftInvoiceWithLines();
        });

        $this->actingAs($user, 'api');
        $this->postJson($this->apiUrl($domain, '/api/invoices/' . $invoice->uuid . '/issue'))->assertStatus(200);

        // Company renamed AFTER issuance - the mapped seller must still
        // show the identity as it was when the invoice was issued.
        $tenant->run(function () {
            CompanyProfile::first()->update(['legal_name' => 'Renamed Later SARL', 'ice' => 'ICE-CHANGED']);
        });

        $tenant->run(function () use ($invoice) {
            $dto = app(InvoiceMapper::class)->map($invoice->fresh());
            $this->assertSame('Original Name SARL', $dto->seller->name);
            $this->assertEquals('ICE-ORIGINAL', $dto->seller->taxIdentifiers['ice']);
            $this->assertNotSame('Renamed Later SARL', $dto->seller->name);
            // Legally numbered once issued - see Invoice::hasLegalNumber().
            $this->assertStringContainsString((string) $invoice->fresh()->invoice_number, $dto->invoiceNumber);
        });

        $tenant->delete();
    }

    /** @test */
    public function mapping_an_invoice_never_changes_it(): void
    {
        [$tenant] = $this->makeTenant('MA');

        $tenant->run(function () {
            $invoice = $this->makeDraftInvoiceWithLines();
            $before  = $invoice->fresh()->getAttributes();

            app(InvoiceMapper::class)->map($invoice);

            $after = Invoice::find($invoice->id)->getAttributes();
            $this->assertSame($before, $after, 'Mapping to EInvoiceData must be read-only.');
        });

        $tenant->delete();
    }

    /** @test */
    public function a_correctly_mapped_invoice_passes_the_generic_ubl_validator(): void
    {
        [$tenant] = $this->makeTenant('MA', ['legal_name' => 'Fakturalista Demo SARL']);

        $tenant->run(function () {
            $invoice = $this->makeDraftInvoiceWithLines();
            $dto = app(InvoiceMapper::class)->map($invoice);

            $issues = (new UblValidator())->validate($dto);
            $this->assertSame([], $issues);
        });

        $tenant->delete();
    }

    /** @test */
    public function tenant_isolation_holds_for_mapped_invoice_data(): void
    {
        [$tenantMa] = $this->makeTenant('MA', ['legal_name' => 'Morocco Co', 'ice' => 'MA-ICE']);
        [$tenantEs] = $this->makeTenant('ES', ['legal_name' => 'Spain Co', 'tax_id' => 'ES-TAXID']);

        $tenantMa->run(function () {
            $dto = app(InvoiceMapper::class)->map($this->makeDraftInvoiceWithLines());
            $this->assertSame('Morocco Co', $dto->seller->name);
            $this->assertSame('MAD', $dto->currency);
            $this->assertArrayNotHasKey('tax_id', $dto->seller->taxIdentifiers);
        });

        $tenantEs->run(function () {
            $dto = app(InvoiceMapper::class)->map($this->makeDraftInvoiceWithLines());
            $this->assertSame('Spain Co', $dto->seller->name);
            $this->assertSame('EUR', $dto->currency);
            $this->assertArrayNotHasKey('ice', $dto->seller->taxIdentifiers);
        });

        $tenantMa->delete();
        $tenantEs->delete();
    }
}
