<?php

namespace Tests\Feature;

use App\Models\{CompanyProfile, Customer, Invoice, Tenant, User};
use App\Services\TenantContextService;
use App\Services\Tax\TaxPresetService;
use Illuminate\Support\Str;
use Tests\TestCase;

class MoroccoProductExperienceTest extends TestCase
{
    private ?Tenant $tenant = null;
    private string $domain;

    private function tenant(string $country, bool $profile = true): void
    {
        $defaults = TenantContextService::defaultsForCountry($country);
        $this->tenant = Tenant::create([
            'id' => 'test-country-product-' . uniqid(), 'country' => $country,
            'currency' => $defaults['currency'], 'language' => $defaults['locale'], 'timezone' => $defaults['timezone'],
        ]);
        $this->domain = $this->tenant->id . '.fakturalista.test';
        $this->tenant->domains()->create(['domain' => $this->domain]);
        $user = $this->tenant->run(function () use ($profile) {
            if ($profile) app(TenantContextService::class)->ensureCompanyProfile()->update(['legal_name' => 'Country test', 'onboarding_completed_at' => now()]);
            return User::factory()->create();
        });
        $this->actingAs($user, 'api');
    }

    private function url(string $path): string
    {
        return 'http://' . $this->domain . '/api/' . $path;
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        $this->tenant?->delete();
        parent::tearDown();
    }

    public static function countryCases(): array
    {
        return [
            'Morocco' => ['MA', 'Morocco', 'MAD', 'fr', 'Africa/Casablanca', 'MA_TVA_20', [20,10,0]],
            'Spain' => ['ES', 'Spain', 'EUR', 'es', 'Europe/Madrid', 'ES_IVA_21', [21,10,4]],
        ];
    }

    /** @dataProvider countryCases */
    public function test_session_settings_onboarding_and_tax_source_agree($country, $name, $currency, $locale, $timezone, $default, $rates): void
    {
        $this->tenant($country);
        foreach (['user', 'settings', 'onboarding'] as $path) {
            $response = $this->getJson($this->url($path))->assertOk();
            foreach (['country' => $country, 'country_name' => $name, 'currency' => $currency, 'locale' => $locale, 'timezone' => $timezone] as $key => $value) {
                $response->assertJsonPath('company_context.' . $key, $value);
            }
        }
        $presets = $this->getJson($this->url('tax-presets'))->assertOk()->assertJsonPath('default_code', $default)->json('presets');
        $this->assertEquals($rates, array_column($presets, 'rate'));
        $this->tenant->run(function () use ($default, $name) {
            $this->assertSame($default, CompanyProfile::first()->default_tax_code);
            $this->assertSame($name, CompanyProfile::first()->country);
        });
    }

    public function test_saved_profile_wins_over_central_spain_data_for_all_product_boundaries(): void
    {
        $this->tenant('ES');
        $this->tenant->run(fn () => CompanyProfile::first()->update([
            'country_code' => 'MA', 'country' => 'Morocco', 'currency' => 'MAD', 'locale' => 'fr',
            'timezone' => 'Africa/Casablanca', 'default_tax_code' => 'MA_TVA_10', 'ice' => 'ICE-test',
        ]));
        $this->getJson($this->url('user'))->assertOk()->assertJsonPath('company_context.country', 'MA')->assertJsonPath('company_context.currency', 'MAD');
        $this->getJson($this->url('settings'))->assertOk()->assertJsonPath('settings.ice', 'ICE-test');
        $this->getJson($this->url('tax-presets'))->assertOk()->assertJsonPath('default_code', 'MA_TVA_10');
        $this->getJson($this->url('settings/verifactu/certificate'))->assertForbidden();
    }

    public function test_settings_save_returns_fresh_country_context_and_keeps_historical_invoice_data(): void
    {
        $this->tenant('ES');
        $before = $this->tenant->run(function () {
            $customer = Customer::factory()->create();
            $invoice = Invoice::create([
                'uuid' => Str::uuid()->toString(), 'reference' => 'LEGACY', 'customer_id' => $customer->id,
                'date' => '2026-09-20', 'expiration_date' => '2026-10-20', 'status' => Invoice::STATUS_ISSUED,
                'issued_at' => now(), 'sub_total' => 100, 'vta' => 21, 'vta21' => 21, 'total' => 121,
                'company_snapshot' => CompanyProfile::first()->identitySnapshot(),
            ]);
            CompanyProfile::first()->update(['city' => 'Madrid', 'state' => 'Catalonia']);
            // fresh(): the in-memory $invoice from create() only carries the
            // attributes explicitly passed to it, not every DB column - a
            // straight ->toArray() here would never match a later full
            // SELECT * (Invoice::first()) even with zero historical drift,
            // making the comparison below meaningless. Pre-existing test bug,
            // unrelated to Morocco Phase 1C.3; fixed incidentally.
            return $invoice->fresh()->load('carts', 'taxLines')->toArray();
        });
        $profile = $this->getJson($this->url('settings'))->assertOk()->json('settings');
        $this->putJson($this->url('settings'), array_merge($profile, [
            'country_code' => 'MA', 'country' => 'Spain', 'currency' => 'MAD', 'locale' => 'fr',
            'timezone' => 'Africa/Casablanca', 'default_tax_code' => 'MA_TVA_20',
        ]))->assertOk()->assertJsonPath('settings.country', 'Morocco')
            ->assertJsonPath('settings.city', 'Madrid')->assertJsonPath('settings.state', 'Catalonia')
            ->assertJsonPath('company_context.country', 'MA')->assertJsonPath('company_context.currency', 'MAD');
        $this->tenant->run(fn () => $this->assertSame($before, Invoice::first()->load('carts', 'taxLines')->toArray()));
        $this->getJson($this->url('settings/verifactu/certificate'))->assertForbidden();
    }

    /** @dataProvider countryCases */
    public function test_new_onboarding_starts_from_the_provisioned_country($country, $name, $currency, $locale, $timezone): void
    {
        $this->tenant($country, false);
        $this->getJson($this->url('onboarding'))->assertOk()->assertJsonPath('profile', null)
            ->assertJsonPath('company_context.country', $country)->assertJsonPath('company_context.country_name', $name)
            ->assertJsonPath('company_context.currency', $currency)->assertJsonPath('company_context.locale', $locale)
            ->assertJsonPath('company_context.timezone', $timezone);
    }

    public function test_spain_remains_selectable_during_morocco_default_onboarding(): void
    {
        $this->tenant('MA', false);
        $this->postJson($this->url('onboarding'), [
            'owner_name' => 'Owner', 'trade_name' => 'Company', 'address_line1' => 'User supplied address',
            'city' => 'User city', 'postal_code' => '12345', 'country' => 'Spain', 'country_code' => 'ES', 'currency' => 'EUR',
        ])->assertOk();
        $this->getJson($this->url('user'))->assertOk()->assertJsonPath('company_context.country', 'ES')
            ->assertJsonPath('company_context.currency', 'EUR')->assertJsonPath('company_context.locale', 'es')
            ->assertJsonPath('company_context.timezone', 'Europe/Madrid');
        $this->getJson($this->url('tax-presets'))->assertOk()->assertJsonPath('default_code', 'ES_IVA_21');
        $this->getJson($this->url('settings/verifactu/certificate'))->assertOk();
        $this->assertSame('ES', $this->tenant->fresh()->country);
    }

    public function test_new_tenant_without_country_metadata_uses_morocco_and_tva_twenty(): void
    {
        $this->tenant('MA', false);
        $this->tenant->update(['country' => null, 'currency' => null, 'language' => null, 'timezone' => null]);
        $this->tenant->run(function () {
            $context = app(TenantContextService::class);
            $profile = $context->ensureCompanyProfile();
            $this->assertSame('MA', $profile->country_code);
            $this->assertSame('MAD', $profile->currency);
            $this->assertSame('fr', $profile->locale);
            $this->assertSame('Africa/Casablanca', $profile->timezone);
            $this->assertSame('MA_TVA_20', app(TaxPresetService::class)->defaultForTenant()->code);
        });
    }
}
