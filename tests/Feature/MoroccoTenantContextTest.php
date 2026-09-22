<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\Tenant;
use App\Services\TenantContextService;
use Tests\TestCase;

/**
 * Morocco Phase 1A - docs/morocco-phase-1a-implementation.md.
 *
 * Covers: a fresh Moroccan tenant gets MA/MAD/fr/Africa-Casablanca, a fresh
 * Spanish tenant still gets ES/EUR/es/Europe-Madrid, an EXISTING tenant's
 * CompanyProfile is never silently converted, and TenantContextService's
 * documented authority order (CompanyProfile > Tenant > hardcoded default).
 */
class MoroccoTenantContextTest extends TestCase
{
    private function makeTenant(array $attributes = []): Tenant
    {
        $id = 'test-mactx-' . uniqid();
        $tenant = Tenant::create(array_merge(['id' => $id], $attributes));
        $tenant->domains()->create(['domain' => $id . '.fakturalista.test']);

        return $tenant;
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }
        parent::tearDown();
    }

    /** @test */
    public function a_new_tenant_provisioned_as_morocco_gets_moroccan_defaults(): void
    {
        $tenant = $this->makeTenant([
            'country' => 'MA', 'currency' => 'MAD', 'language' => 'fr', 'timezone' => 'Africa/Casablanca',
        ]);

        $tenant->run(function () {
            $profile = app(TenantContextService::class)->ensureCompanyProfile();

            $this->assertEquals('MA', $profile->country_code);
            $this->assertEquals('MAD', $profile->currency);
            $this->assertEquals('fr', $profile->locale);
            $this->assertEquals('Africa/Casablanca', $profile->timezone);
        });

        $tenant->delete();
    }

    /** @test */
    public function a_new_tenant_provisioned_as_spain_gets_spanish_defaults(): void
    {
        $tenant = $this->makeTenant([
            'country' => 'ES', 'currency' => 'EUR', 'language' => 'es', 'timezone' => 'Europe/Madrid',
        ]);

        $tenant->run(function () {
            $profile = app(TenantContextService::class)->ensureCompanyProfile();

            $this->assertEquals('ES', $profile->country_code);
            $this->assertEquals('EUR', $profile->currency);
            $this->assertEquals('es', $profile->locale);
            $this->assertEquals('Europe/Madrid', $profile->timezone);
        });

        $tenant->delete();
    }

    /** @test */
    public function an_existing_spanish_tenant_is_never_converted_to_morocco(): void
    {
        // Simulates a tenant that existed before Morocco Phase 1A: its
        // central Tenant record has no country/currency/language/timezone
        // at all (those columns didn't exist when it was created), but its
        // CompanyProfile already holds real, persisted Spanish values.
        $tenant = $this->makeTenant();

        $tenant->run(function () {
            CompanyProfile::create([
                'legal_name'   => 'Empresa Existente SL',
                'country_code' => 'ES',
                'currency'     => 'EUR',
                'locale'       => 'es',
                'timezone'     => 'Europe/Madrid',
            ]);

            // Calling ensureCompanyProfile() again (e.g. a later request
            // touching Stripe/onboarding) must never overwrite this row.
            $profile = app(TenantContextService::class)->ensureCompanyProfile();

            $this->assertEquals('ES', $profile->country_code);
            $this->assertEquals('EUR', $profile->currency);
            $this->assertEquals('es', $profile->locale);
            $this->assertEquals('Europe/Madrid', $profile->timezone);
            $this->assertEquals(1, CompanyProfile::count(), 'A second row must never be created.');
        });

        $tenant->delete();
    }

    /** @test */
    public function company_profile_is_authoritative_over_the_tenant_record_when_both_disagree(): void
    {
        // Tenant says Morocco, but this tenant's own CompanyProfile (e.g.
        // edited later in Settings) says Spain - CompanyProfile must win.
        $tenant = $this->makeTenant(['country' => 'MA', 'currency' => 'MAD', 'language' => 'fr']);

        $tenant->run(function () {
            CompanyProfile::create([
                'legal_name'   => 'Override SL',
                'country_code' => 'ES',
                'currency'     => 'EUR',
                'locale'       => 'es',
            ]);

            $context = app(TenantContextService::class);

            $this->assertEquals('ES', $context->country());
            $this->assertEquals('EUR', $context->currency());
            $this->assertEquals('es', $context->locale());
            $this->assertTrue($context->isSpain());
        });

        $tenant->delete();
    }

    /** @test */
    public function tenant_record_is_the_fallback_when_no_company_profile_exists_yet(): void
    {
        $tenant = $this->makeTenant([
            'country' => 'MA', 'currency' => 'MAD', 'language' => 'fr', 'timezone' => 'Africa/Casablanca',
        ]);

        $tenant->run(function () {
            $this->assertNull(CompanyProfile::first());

            $context = app(TenantContextService::class);

            $this->assertEquals('MA', $context->country());
            $this->assertEquals('MAD', $context->currency());
            $this->assertEquals('fr', $context->locale());
            $this->assertEquals('Africa/Casablanca', $context->timezone());
        });

        $tenant->delete();
    }

    /** @test */
    public function morocco_is_the_ultimate_fallback_when_nothing_is_configured_at_all(): void
    {
        $tenant = $this->makeTenant();

        $tenant->run(function () {
            $this->assertNull(CompanyProfile::first());

            $context = app(TenantContextService::class);

            $this->assertEquals(TenantContextService::DEFAULT_COUNTRY, $context->country());
            $this->assertEquals(TenantContextService::DEFAULT_CURRENCY, $context->currency());
            $this->assertEquals(TenantContextService::DEFAULT_LOCALE, $context->locale());
            $this->assertEquals(TenantContextService::DEFAULT_TIMEZONE, $context->timezone());
        });

        $tenant->delete();
    }

    /** @test */
    public function is_spain_is_false_for_a_moroccan_tenant_and_true_for_a_spanish_one(): void
    {
        $ma = $this->makeTenant(['country' => 'MA']);
        $ma->run(fn () => $this->assertFalse(app(TenantContextService::class)->isSpain()));
        $ma->delete();

        $es = $this->makeTenant(['country' => 'ES']);
        $es->run(fn () => $this->assertTrue(app(TenantContextService::class)->isSpain()));
        $es->delete();
    }
}
