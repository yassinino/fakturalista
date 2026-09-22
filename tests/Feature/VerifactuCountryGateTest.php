<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;

/**
 * Morocco Phase 1A §6 - docs/morocco-phase-1a-implementation.md.
 *
 * VERI*FACTU stays fully intact for Spain (nothing under
 * app/Services/Verifactu/* changes) but must be unreachable for a
 * Moroccan tenant - not just hidden in the UI, refused server-side too,
 * so a Moroccan tenant manually visiting the URL still gets nothing.
 */
class VerifactuCountryGateTest extends TestCase
{
    private function makeTenant(string $countryCode): array
    {
        $id = 'test-vfgate-' . uniqid();
        $tenant = Tenant::create(['id' => $id]);
        $domain = $id . '.fakturalista.test';
        $tenant->domains()->create(['domain' => $domain]);

        tenancy()->initialize($tenant);
        $user = User::factory()->create();
        CompanyProfile::create([
            'legal_name'              => 'Test Co',
            'country_code'            => $countryCode,
            'tax_id'                  => '89890001K',
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
    public function moroccan_tenant_is_refused_on_every_verifactu_certificate_route(): void
    {
        [$tenant, $user, $domain] = $this->makeTenant('MA');
        $this->actingAs($user, 'api');

        $this->getJson($this->apiUrl($domain, '/api/settings/verifactu/certificate'))
            ->assertStatus(403)
            ->assertJsonPath('error', 'not_available_for_country');

        $this->postJson($this->apiUrl($domain, '/api/settings/verifactu/certificate'), [])
            ->assertStatus(403)
            ->assertJsonPath('error', 'not_available_for_country');

        $this->deleteJson($this->apiUrl($domain, '/api/settings/verifactu/certificate'))
            ->assertStatus(403)
            ->assertJsonPath('error', 'not_available_for_country');

        $tenant->delete();
    }

    /** @test */
    public function spanish_tenant_can_still_reach_the_verifactu_certificate_endpoint(): void
    {
        [$tenant, $user, $domain] = $this->makeTenant('ES');
        $this->actingAs($user, 'api');

        $this->getJson($this->apiUrl($domain, '/api/settings/verifactu/certificate'))
            ->assertStatus(200)
            ->assertJsonPath('configured', false)
            ->assertJsonPath('environment', 'test');

        $tenant->delete();
    }

    /** @test */
    public function moroccan_tenant_is_still_refused_even_if_a_certificate_row_happens_to_exist(): void
    {
        // Defense-in-depth: the gate must be based on country, not merely
        // on the absence of certificate data.
        [$tenant, $user, $domain] = $this->makeTenant('MA');

        $tenant->run(function () {
            \App\Models\Verifactu\VerifactuCertificate::create([
                'nif'                 => '89890001K',
                'encrypted_file_path' => 'verifactu-certificates/does-not-matter.p12.enc',
                'passphrase'          => 'irrelevant',
                'subject'             => 'CN=Should Not Matter',
                'issuer'              => 'CN=Should Not Matter',
                'serial_number'       => '1',
                'valid_from'          => now()->subDay(),
                'valid_to'            => now()->addYear(),
            ]);
        });

        $this->actingAs($user, 'api');

        $this->getJson($this->apiUrl($domain, '/api/settings/verifactu/certificate'))
            ->assertStatus(403)
            ->assertJsonPath('error', 'not_available_for_country');

        $tenant->delete();
    }
}
