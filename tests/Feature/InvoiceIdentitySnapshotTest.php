<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Verifactu\VerifactuChainService;
use App\Services\Verifactu\VerifactuHashService;
use App\Services\Verifactu\VerifactuXmlBuilder;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Morocco Phase 1B §7 - docs/morocco-phase-1b-identity.md.
 *
 * The generic (non-VERI*FACTU) invoice identity snapshot: an issued
 * invoice must keep showing the seller/customer identity exactly as it
 * was at issuance, regardless of later edits to CompanyProfile/Customer.
 * Covers H, I, J, K, L, N.
 */
class InvoiceIdentitySnapshotTest extends TestCase
{
    private function makeTenant(string $countryCode, array $companyOverrides = []): array
    {
        $id     = 'test-snap-' . uniqid();
        $tenant = Tenant::create(['id' => $id]);
        $domain = $id . '.fakturalista.test';
        $tenant->domains()->create(['domain' => $domain]);

        tenancy()->initialize($tenant);
        $user = User::factory()->create();
        $company = CompanyProfile::create(array_merge([
            'legal_name'              => 'Test Co',
            'country_code'            => $countryCode,
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

    private function makeF1Invoice(array $customerOverrides = []): Invoice
    {
        $customer = Customer::factory()->create(array_merge(['type' => 1], $customerOverrides));

        return Invoice::create([
            'uuid'            => Str::uuid()->toString(),
            'reference'       => 'INV-SNAP-' . uniqid(),
            'customer_id'     => $customer->id,
            'date'            => now()->toDateString(),
            'expiration_date' => now()->addDays(30)->toDateString(),
            'status'          => Invoice::STATUS_DRAFT,
            'sub_total'       => 100.00,
            'total'           => 121.00,
            'vta'             => 21.00,
            'vta4'            => 0,
            'vta10'           => 0,
            'vta21'           => 21.00,
            'discount_rate'   => 0,
            'discount_amount' => 0,
            'invoice_type'    => Invoice::TYPE_F1,
            'descripcion_operacion' => 'Test',
        ]);
    }

    /** @test */
    public function h_moroccan_invoice_issuance_is_not_blocked_by_absent_customer_ice(): void
    {
        [$tenant, $user, $domain] = $this->makeTenant('MA');

        $invoice = null;
        $tenant->run(function () use (&$invoice) {
            $invoice = $this->makeF1Invoice(['ice' => null, 'tax_id' => null, 'vat_number' => null]);
        });

        $this->actingAs($user, 'api');
        $response = $this->postJson($this->apiUrl($domain, '/api/invoices/' . $invoice->uuid . '/issue'));

        $response->assertStatus(200);
        $tenant->run(function () use ($invoice) {
            $this->assertTrue($invoice->fresh()->isIssued());
        });

        $tenant->delete();
    }

    /** @test */
    public function i_issued_moroccan_invoice_snapshots_seller_ice_if_and_registration_number(): void
    {
        [$tenant, $user, $domain] = $this->makeTenant('MA', [
            'legal_name' => 'Fakturalista Demo SARL', 'ice' => '001234567000089',
            'if_number' => '12345678', 'registration_number' => '9999 - Casablanca',
        ]);

        $invoice = null;
        $tenant->run(function () use (&$invoice) {
            $invoice = $this->makeF1Invoice(['ice' => '001111222000033']);
        });

        $this->actingAs($user, 'api');
        $this->postJson($this->apiUrl($domain, '/api/invoices/' . $invoice->uuid . '/issue'))->assertStatus(200);

        $tenant->run(function () use ($invoice) {
            $fresh = $invoice->fresh();
            $this->assertEquals('Fakturalista Demo SARL', $fresh->company_snapshot['legal_name']);
            $this->assertEquals('001234567000089', $fresh->company_snapshot['ice']);
            $this->assertEquals('12345678', $fresh->company_snapshot['if_number']);
            $this->assertEquals('9999 - Casablanca', $fresh->company_snapshot['registration_number']);
            $this->assertEquals('001111222000033', $fresh->customer_snapshot['ice']);
        });

        $tenant->delete();
    }

    /** @test */
    public function j_changing_company_profile_after_issuance_does_not_change_the_historical_snapshot(): void
    {
        [$tenant, $user, $domain] = $this->makeTenant('MA', ['legal_name' => 'Original Name SARL', 'ice' => 'ICE-ORIGINAL']);

        $invoice = null;
        $tenant->run(function () use (&$invoice) {
            $invoice = $this->makeF1Invoice();
        });

        $this->actingAs($user, 'api');
        $this->postJson($this->apiUrl($domain, '/api/invoices/' . $invoice->uuid . '/issue'))->assertStatus(200);

        // Company changes its name and ICE a month later.
        $tenant->run(function () {
            CompanyProfile::first()->update(['legal_name' => 'Renamed Company SARL', 'ice' => 'ICE-CHANGED']);
        });

        $tenant->run(function () use ($invoice) {
            $fresh = $invoice->fresh();
            $this->assertEquals('Original Name SARL', $fresh->company_snapshot['legal_name'], 'The historical invoice must keep the identity at issuance time.');
            $this->assertEquals('ICE-ORIGINAL', $fresh->company_snapshot['ice']);
            $this->assertNotEquals('Renamed Company SARL', $fresh->company_snapshot['legal_name']);
        });

        $tenant->delete();
    }

    /** @test */
    public function k_customer_identity_is_also_immutable_after_issuance(): void
    {
        [$tenant, $user, $domain] = $this->makeTenant('MA');

        $invoice   = null;
        $customerId = null;
        $tenant->run(function () use (&$invoice, &$customerId) {
            $invoice = $this->makeF1Invoice(['company_name' => 'Client Original SARL', 'ice' => 'CLIENT-ICE-ORIGINAL']);
            $customerId = $invoice->customer_id;
        });

        $this->actingAs($user, 'api');
        $this->postJson($this->apiUrl($domain, '/api/invoices/' . $invoice->uuid . '/issue'))->assertStatus(200);

        $tenant->run(function () use ($customerId) {
            Customer::find($customerId)->update(['company_name' => 'Client Renamed SARL', 'ice' => 'CLIENT-ICE-CHANGED']);
        });

        $tenant->run(function () use ($invoice) {
            $fresh = $invoice->fresh();
            $this->assertEquals('Client Original SARL', $fresh->customer_snapshot['company_name']);
            $this->assertEquals('CLIENT-ICE-ORIGINAL', $fresh->customer_snapshot['ice']);
        });

        $tenant->delete();
    }

    /** @test */
    public function l_moroccan_style_fields_never_appear_in_the_verifactu_xml_even_when_populated_on_a_spanish_tenant(): void
    {
        config([
            'verifactu.producer.name' => 'Fakturalista Test Producer',
            'verifactu.producer.id_country' => 'MA',
            'verifactu.producer.id_type' => '06',
            'verifactu.producer.id' => 'RC-TEST-12345',
            'verifactu.system.name' => 'Fakturalista',
            'verifactu.system.id' => 'FK',
            'verifactu.system.version' => '1.0.0-test',
            'verifactu.system.only_verifactu' => true,
            'verifactu.system.multi_ot' => true,
        ]);

        [$tenant, , , $company] = $this->makeTenant('ES', [
            'tax_id' => '89890001K',
            'verifactu_installation_number' => 'TEST-INSTALL-1',
            // Deliberately also populated, to prove these never leak into
            // AEAT XML regardless of whether they happen to hold a value.
            'ice' => 'SHOULD-NEVER-APPEAR-ICE',
            'if_number' => 'SHOULD-NEVER-APPEAR-IF',
        ]);

        $xml = $tenant->run(function () use ($company) {
            $customer = Customer::factory()->create([
                'tax_id' => 'B00000000',
                'ice' => 'SHOULD-NEVER-APPEAR-CUSTOMER-ICE',
                'commercial_register' => 'SHOULD-NEVER-APPEAR-RC',
            ]);
            $invoice = $this->makeF1Invoice();
            $invoice->update(['customer_id' => $customer->id]);

            app(\App\Services\InvoiceNumberingService::class)->assignLegalNumber($invoice, $company->fresh());
            $invoice->status = Invoice::STATUS_ISSUED;
            $invoice->save();

            $record = (new VerifactuChainService(new VerifactuHashService()))->recordAlta($invoice, $company->fresh());

            return app(VerifactuXmlBuilder::class)->build($record);
        });

        $this->assertStringNotContainsString('SHOULD-NEVER-APPEAR', $xml);

        $tenant->delete();
    }

    /** @test */
    public function issued_moroccan_invoice_pdf_renders_the_ice_if_and_rc_block_without_crashing(): void
    {
        [$tenant, $user, $domain] = $this->makeTenant('MA', [
            'legal_name' => 'Fakturalista Demo SARL', 'ice' => '001234567000089',
            'if_number' => '12345678', 'registration_number' => '9999 - Casablanca',
        ]);

        $invoice = null;
        $tenant->run(function () use (&$invoice) {
            $invoice = $this->makeF1Invoice();
        });

        $this->actingAs($user, 'api');
        $this->postJson($this->apiUrl($domain, '/api/invoices/' . $invoice->uuid . '/issue'))->assertStatus(200);

        $tenant->run(function () use ($invoice) {
            $pdf = app(\App\Services\Pdf\TemplateRendererService::class)->render($invoice->fresh(), 'invoice');
            $this->assertStringStartsWith('%PDF', $pdf);
        });

        $tenant->delete();
    }

    /** @test */
    public function n_tenant_isolation_holds_for_identity_snapshots(): void
    {
        [$tenantMa, $userMa, $domainMa] = $this->makeTenant('MA', ['ice' => 'MA-ICE-VALUE']);
        [$tenantEs, $userEs, $domainEs] = $this->makeTenant('ES', ['tax_id' => 'ES-TAXID-VALUE']);

        $invoiceMa = null;
        $tenantMa->run(function () use (&$invoiceMa) {
            $invoiceMa = $this->makeF1Invoice();
        });
        $this->actingAs($userMa, 'api');
        $this->postJson($this->apiUrl($domainMa, '/api/invoices/' . $invoiceMa->uuid . '/issue'))->assertStatus(200);

        $invoiceEs = null;
        $tenantEs->run(function () use (&$invoiceEs) {
            $invoiceEs = $this->makeF1Invoice();
        });
        $this->actingAs($userEs, 'api');
        $this->postJson($this->apiUrl($domainEs, '/api/invoices/' . $invoiceEs->uuid . '/issue'))->assertStatus(200);

        $tenantMa->run(function () use ($invoiceMa) {
            $this->assertEquals('MA-ICE-VALUE', $invoiceMa->fresh()->company_snapshot['ice']);
            $this->assertNull($invoiceMa->fresh()->company_snapshot['tax_id']);
        });
        $tenantEs->run(function () use ($invoiceEs) {
            $this->assertEquals('ES-TAXID-VALUE', $invoiceEs->fresh()->company_snapshot['tax_id']);
            $this->assertNull($invoiceEs->fresh()->company_snapshot['ice']);
        });

        $tenantMa->delete();
        $tenantEs->delete();
    }
}
