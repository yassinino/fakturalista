<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;

/**
 * Morocco Phase 1B - docs/morocco-phase-1b-identity.md.
 *
 * Covers items A-G: Moroccan CompanyProfile/Customer can store ICE/IF/RC,
 * Settings exposes them for a Moroccan tenant, Spain's NIF/VAT/Registro
 * Mercantil are unaffected, and no customer is ever required to have any
 * of ICE/IF/RC.
 */
class MoroccoIdentityTest extends TestCase
{
    private function makeTenant(string $countryCode): array
    {
        $id     = 'test-maid-' . uniqid();
        $tenant = Tenant::create(['id' => $id]);
        $domain = $id . '.fakturalista.test';
        $tenant->domains()->create(['domain' => $domain]);

        tenancy()->initialize($tenant);
        $user = User::factory()->create();
        CompanyProfile::create([
            'legal_name'              => 'Test Co',
            'country_code'            => $countryCode,
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

    /** @test */
    public function moroccan_settings_endpoint_stores_and_returns_ice_if_and_registration_number(): void
    {
        [$tenant, $user, $domain] = $this->makeTenant('MA');
        $this->actingAs($user, 'api');

        $response = $this->postJson($this->apiUrl($domain, '/api/settings'), [
            '_method'              => 'put',
            'legal_name'           => 'Entreprise Marocaine SARL',
            'country_code'         => 'MA',
            'ice'                  => '001234567000089',
            'if_number'            => '12345678',
            'registration_number'  => '12345 - Casablanca',
            'invoice_prefix'       => 'INV',
            'invoice_next_number'  => 1,
            'invoice_number_format'=> '{PREFIX}-{YYYY}-{NUMBER}',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('settings.ice', '001234567000089')
            ->assertJsonPath('settings.if_number', '12345678')
            ->assertJsonPath('settings.registration_number', '12345 - Casablanca');

        // D: also confirmed via a plain read, exactly what the Settings UI fetches.
        $this->getJson($this->apiUrl($domain, '/api/settings'))
            ->assertStatus(200)
            ->assertJsonPath('settings.ice', '001234567000089')
            ->assertJsonPath('settings.if_number', '12345678');

        $tenant->delete();
    }

    /** @test */
    public function spanish_settings_endpoint_still_stores_nif_vat_and_registration_number_unchanged(): void
    {
        [$tenant, $user, $domain] = $this->makeTenant('ES');
        $this->actingAs($user, 'api');

        $response = $this->postJson($this->apiUrl($domain, '/api/settings'), [
            '_method'              => 'put',
            'legal_name'           => 'Empresa Española SL',
            'country_code'         => 'ES',
            'tax_id'               => 'B12345678',
            'vat_number'           => 'ES-B12345678',
            'registration_number'  => 'RM Madrid T-12345',
            'invoice_prefix'       => 'INV',
            'invoice_next_number'  => 1,
            'invoice_number_format'=> '{PREFIX}-{YYYY}-{NUMBER}',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('settings.tax_id', 'B12345678')
            ->assertJsonPath('settings.vat_number', 'ES-B12345678')
            ->assertJsonPath('settings.registration_number', 'RM Madrid T-12345')
            ->assertJsonPath('settings.ice', null)
            ->assertJsonPath('settings.if_number', null);

        $tenant->delete();
    }

    /** @test */
    public function moroccan_business_customer_can_store_ice_if_and_commercial_register(): void
    {
        [$tenant, $user, $domain] = $this->makeTenant('MA');
        $this->actingAs($user, 'api');

        $response = $this->postJson($this->apiUrl($domain, '/api/customers'), [
            'name'                 => 'Client Business SARL',
            'type'                 => 1,
            'ice'                  => '001111222000033',
            'if_number'            => '9988776',
            'commercial_register'  => '5566 - Rabat',
            'contacts'             => [],
        ]);

        $response->assertStatus(200);

        $tenant->run(function () {
            $customer = \App\Models\Customer::where('company_name', 'Client Business SARL')->first();
            $this->assertNotNull($customer);
            $this->assertTrue($customer->isBusiness());
            $this->assertEquals('001111222000033', $customer->ice);
            $this->assertEquals('9988776', $customer->if_number);
            $this->assertEquals('5566 - Rabat', $customer->commercial_register);
        });

        $tenant->delete();
    }

    /** @test */
    public function moroccan_customer_without_any_fiscal_identifier_can_still_be_created(): void
    {
        [$tenant, $user, $domain] = $this->makeTenant('MA');
        $this->actingAs($user, 'api');

        $response = $this->postJson($this->apiUrl($domain, '/api/customers'), [
            'first_name' => 'Amina',
            'last_name'  => 'Bennani',
            'type'       => 2,
            'contacts'   => [],
        ]);

        $response->assertStatus(200);

        $tenant->run(function () {
            $customer = \App\Models\Customer::where('first_name', 'Amina')->first();
            $this->assertNotNull($customer, 'A customer with no ICE/IF/RC must still be creatable.');
            $this->assertTrue($customer->isIndividual());
            $this->assertNull($customer->ice);
            $this->assertNull($customer->if_number);
            $this->assertNull($customer->commercial_register);
        });

        $tenant->delete();
    }
}
