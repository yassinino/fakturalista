<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;

/**
 * Phase 2C.1: Customer.tax_id (the Spanish NIF column added in Phase 2A)
 * had no UI or controller path to actually set it - the customer form's
 * field labeled "NIF" was bound to `ice` (the Moroccan identifier)
 * instead. Confirmed via direct inspection of CustomerController.php
 * (store()/update()/edit()/show() never read/wrote `tax_id` at all) and
 * resources/js/views/admin/customers/{create,edit}.vue (v-model="state.ice"
 * under a "NIF" label). Fixed alongside the foreign-ID (IDOtro) work
 * since both concern the same "how is this customer identified for
 * VERI*FACTU" question.
 */
class CustomerTaxIdentificationTest extends TestCase
{
    protected Tenant $tenant;
    protected User $user;
    protected string $domain;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['id' => 'test-customer-tax-' . uniqid()]);
        $this->domain = 'test-customer-tax-' . uniqid() . '.fakturalista.test';
        $this->tenant->domains()->create(['domain' => $this->domain]);

        tenancy()->initialize($this->tenant);

        $this->user = User::factory()->create();

        CompanyProfile::create([
            'legal_name'              => 'Test Company S.L.',
            'tax_id'                  => 'B12345678',
            'invoice_prefix'          => 'INV',
            'onboarding_completed_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        $this->tenant->delete();
        parent::tearDown();
    }

    private function apiUrl(string $path): string
    {
        return 'http://' . $this->domain . $path;
    }

    /** @test */
    public function creating_a_customer_with_a_spanish_nif_persists_it(): void
    {
        $this->actingAs($this->user, 'api');

        $response = $this->postJson($this->apiUrl('/api/customers'), [
            'type'    => 1,
            'name'    => 'Cliente Español SL',
            'tax_id'  => 'B87654321',
            'contacts' => [],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('customers', [
            'company_name' => 'Cliente Español SL',
            'tax_id'       => 'B87654321',
        ]);
    }

    /** @test */
    public function creating_a_customer_with_a_foreign_id_persists_type_and_value(): void
    {
        $this->actingAs($this->user, 'api');

        $response = $this->postJson($this->apiUrl('/api/customers'), [
            'type'                => 1,
            'name'                => 'Foreign Client Ltd',
            'foreign_tax_id_type' => '03',
            'foreign_tax_id'      => 'GB-PASSPORT-1',
            'contacts'            => [],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('customers', [
            'company_name'        => 'Foreign Client Ltd',
            'foreign_tax_id_type' => '03',
            'foreign_tax_id'      => 'GB-PASSPORT-1',
        ]);
    }

    /** @test */
    public function an_invalid_foreign_id_type_is_rejected(): void
    {
        $this->actingAs($this->user, 'api');

        $response = $this->postJson($this->apiUrl('/api/customers'), [
            'type'                => 1,
            'name'                => 'Cliente Inválido',
            'foreign_tax_id_type' => '99', // not in lista L7
            'foreign_tax_id'      => 'X',
            'contacts'            => [],
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function updating_a_customer_persists_the_nif_and_edit_endpoint_returns_it(): void
    {
        $customer = Customer::factory()->withoutTaxId()->create(['company_name' => 'Cliente a Actualizar']);
        $this->actingAs($this->user, 'api');

        $update = $this->putJson($this->apiUrl('/api/customers/' . $customer->uuid), [
            'type'    => 1,
            'name'    => 'Cliente a Actualizar',
            'tax_id'  => 'B11223344',
            'contacts' => [],
        ]);
        $update->assertStatus(200);

        $edit = $this->getJson($this->apiUrl('/api/customers/' . $customer->uuid . '/edit'));
        $edit->assertStatus(200)
             ->assertJsonPath('customer.tax_id', 'B11223344');
    }

    /** @test */
    public function existing_customers_without_the_new_fields_remain_valid(): void
    {
        // Purely additive migration - a customer created before this phase
        // (NULL in both new columns) must still load/show/edit correctly.
        $customer = Customer::factory()->create(['tax_id' => 'B00000001']);

        $this->assertNull($customer->foreign_tax_id_type);
        $this->assertNull($customer->foreign_tax_id);
        $this->assertFalse($customer->hasForeignTaxId());

        $this->actingAs($this->user, 'api');
        $response = $this->getJson($this->apiUrl('/api/customers/' . $customer->uuid . '/edit'));
        $response->assertStatus(200)
                 ->assertJsonPath('customer.tax_id', 'B00000001');
    }
}
