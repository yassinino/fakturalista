<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Morocco Phase 1A.1 - docs/morocco-phase-1a1-country-boundary-cleanup.md.
 *
 * InvoiceController::issueInvoice()'s F1-requires-customer-NIF check
 * exists because of RD 1619/2012 art. 6 (Spanish law) - it must keep
 * blocking Spanish tenants exactly as before, but must never block a
 * Moroccan tenant, which has no such rule (and no Moroccan customer-
 * identity rule is being invented here - that's Phase 1B).
 */
class InvoiceCountryBoundaryTest extends TestCase
{
    private function makeTenant(string $countryCode, string $companyTaxId): array
    {
        $id     = 'test-invboundary-' . uniqid();
        $tenant = Tenant::create(['id' => $id]);
        $domain = $id . '.fakturalista.test';
        $tenant->domains()->create(['domain' => $domain]);

        tenancy()->initialize($tenant);
        $user = User::factory()->create();
        CompanyProfile::create([
            'legal_name'              => 'Test Co',
            'country_code'            => $countryCode,
            'tax_id'                  => $companyTaxId,
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

    private function makeF1InvoiceWithoutCustomerTaxId(): Invoice
    {
        $customer = Customer::factory()->withoutTaxId()->create();

        return Invoice::create([
            'uuid'            => Str::uuid()->toString(),
            'reference'       => 'INV-BOUNDARY-1',
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
            'descripcion_operacion' => 'Servicios de prueba',
        ]);
    }

    /** @test */
    public function spanish_tenant_is_still_blocked_from_issuing_f1_without_customer_tax_id(): void
    {
        [$tenant, $user, $domain] = $this->makeTenant('ES', 'B12345678');

        $invoice = null;
        $tenant->run(function () use (&$invoice) {
            $invoice = $this->makeF1InvoiceWithoutCustomerTaxId();
        });

        $this->actingAs($user, 'api');
        $response = $this->postJson($this->apiUrl($domain, '/api/invoices/' . $invoice->uuid . '/issue'));

        $response->assertStatus(422);
        $tenant->run(function () use ($invoice) {
            $this->assertTrue($invoice->fresh()->isDraft(), 'A Spanish F1 invoice without a customer NIF must not be issuable.');
        });

        $tenant->delete();
    }

    /** @test */
    public function moroccan_tenant_is_not_blocked_by_the_spanish_customer_nif_rule(): void
    {
        [$tenant, $user, $domain] = $this->makeTenant('MA', '001234567000089');

        $invoice = null;
        $tenant->run(function () use (&$invoice) {
            $invoice = $this->makeF1InvoiceWithoutCustomerTaxId();
        });

        $this->actingAs($user, 'api');
        $response = $this->postJson($this->apiUrl($domain, '/api/invoices/' . $invoice->uuid . '/issue'));

        $response->assertStatus(200);
        $tenant->run(function () use ($invoice) {
            $this->assertTrue($invoice->fresh()->isIssued(), 'A Moroccan tenant must not be blocked by the Spain-only F1/NIF rule.');
        });

        $tenant->delete();
    }
}
