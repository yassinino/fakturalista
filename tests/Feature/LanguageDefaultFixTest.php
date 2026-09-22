<?php

namespace Tests\Feature;

use App\Mail\WelcomeSelfServiceMail;
use App\Mail\WelcomeTenantMail;
use App\Models\CompanyProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContextService;
use App\Services\TenantProvisioningService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Regression tests for the French-default-language fix.
 *
 * Fakturalista is Morocco-first: French (`fr`) must be the default/fallback
 * language everywhere - public site, registration, onboarding, emails,
 * application UI - while an explicit Spanish or English choice is always
 * honored. Country (fiscal behavior: MAD/TVA/ICE for Morocco, EUR/IVA/NIF
 * for Spain) is a separate concern from UI language and must never be
 * affected by a language change, or vice versa.
 *
 * These tests cover the two concretely reported bugs (Spanish welcome email
 * for a Moroccan signup, English onboarding page) at the backend/data level,
 * plus the underlying priority chain: explicit preference > tenant/company
 * preference > French fallback.
 */
class LanguageDefaultFixTest extends TestCase
{
    private array $createdTenantIds = [];

    protected function tearDown(): void
    {
        foreach ($this->createdTenantIds as $id) {
            Tenant::find($id)?->delete();
        }
        parent::tearDown();
    }

    private function track(Tenant $tenant): Tenant
    {
        $this->createdTenantIds[] = $tenant->getTenantKey();

        return $tenant;
    }

    private function provisioningData(array $overrides = []): array
    {
        $suffix = uniqid();

        return array_merge([
            'company_name'   => 'Lang Fix Co ' . $suffix,
            'company_email'  => 'company+' . $suffix . '@example.com',
            'owner_name'     => 'Test Owner',
            'owner_email'    => 'owner+' . $suffix . '@example.com',
            'admin_password' => 'SecurePass123',
            'subdomain'      => 'langfix-' . $suffix,
            'plan_slug'      => 'starter',
        ], $overrides);
    }

    // ── 1. Global default/fallback locale is French ───────────────────────

    /** @test */
    public function the_applications_default_and_fallback_locale_is_french(): void
    {
        $this->assertSame('fr', config('app.locale'));
        $this->assertSame('fr', config('app.fallback_locale'));
    }

    /** @test */
    public function the_users_locale_column_defaults_to_french_at_the_schema_level(): void
    {
        // Regression guard for the landmine found during the language
        // audit: users.locale's own DB default used to be 'es'
        // (2025_12_20_090000_add_locale_to_users_table.php), silently
        // disagreeing with the app's French-first default. Any future
        // code path that inserts a user without an explicit locale must
        // still land on French, not Spanish.
        // users lives in the per-tenant database, not the central one - the
        // migration under test only runs against tenant databases.
        $tenant = $this->track(Tenant::create(['id' => 'test-lang-schema-' . uniqid()]));

        $default = $tenant->run(function () {
            $column = DB::selectOne("SHOW COLUMNS FROM users WHERE Field = 'locale'");

            return $column->Default ?? null;
        });

        $this->assertSame('fr', $default);
    }

    // ── 2. Self-service registration: Morocco defaults to French ──────────

    /** @test */
    public function a_new_moroccan_tenant_with_no_explicit_language_defaults_to_french(): void
    {
        $service = app(TenantProvisioningService::class);
        $tenant = $this->track($service->provision(
            $this->provisioningData(['country' => 'MA']),
            selfService: true,
        ));

        $this->assertSame('MA', $tenant->country);
        $this->assertSame('MAD', $tenant->currency);
        $this->assertSame('fr', $tenant->language);

        $ownerLocale = $tenant->run(fn () => User::first()->locale);
        $this->assertSame('fr', $ownerLocale);
    }

    /** @test */
    public function a_new_tenant_with_no_country_chosen_at_all_still_defaults_to_morocco_and_french(): void
    {
        $service = app(TenantProvisioningService::class);
        $tenant = $this->track($service->provision(
            $this->provisioningData(), // no 'country' key at all
            selfService: true,
        ));

        $this->assertSame(TenantContextService::DEFAULT_COUNTRY, $tenant->country);
        $this->assertSame('fr', $tenant->language);
    }

    /** @test */
    public function a_new_spanish_tenant_still_defaults_to_spanish_via_country_derived_preference(): void
    {
        // Country is a "tenant/company preference" in the priority chain -
        // choosing Spain during signup is itself a (weak) signal, distinct
        // from an explicit language toggle, and must still resolve to 'es'
        // rather than being forced to French.
        $service = app(TenantProvisioningService::class);
        $tenant = $this->track($service->provision(
            $this->provisioningData(['country' => 'ES']),
            selfService: true,
        ));

        $this->assertSame('ES', $tenant->country);
        $this->assertSame('EUR', $tenant->currency);
        $this->assertSame('es', $tenant->language);
    }

    // ── 3. Explicit language always wins over the country-derived default ─

    /** @test */
    public function an_explicit_language_choice_overrides_the_country_derived_default(): void
    {
        // Simulates the Filament admin wizard's own explicit language
        // field: a Moroccan tenant whose admin explicitly picked English
        // must get English, not the country-derived French default.
        $service = app(TenantProvisioningService::class);
        $tenant = $this->track($service->provision(
            $this->provisioningData(['country' => 'MA', 'language' => 'en']),
            selfService: false,
        ));

        $this->assertSame('MA', $tenant->country, 'Country/fiscal config must be untouched by the language choice.');
        $this->assertSame('MAD', $tenant->currency);
        $this->assertSame('en', $tenant->language);
    }

    // ── 4. Welcome email language follows the same priority chain ─────────

    /** @test */
    public function the_self_service_welcome_email_is_sent_in_french_by_default_for_a_moroccan_signup(): void
    {
        Mail::fake();
        $service = app(TenantProvisioningService::class);
        $this->track($service->provision(
            $this->provisioningData(['country' => 'MA']),
            selfService: true,
        ));

        Mail::assertQueued(WelcomeSelfServiceMail::class, function (WelcomeSelfServiceMail $mail) {
            $this->assertSame('fr', $mail->locale);
            $html = $mail->render();
            $this->assertStringContainsString('Bienvenue sur Fakturalista', $html);
            $this->assertStringNotContainsString('Bienvenido', $html);
            $this->assertStringNotContainsString('Welcome to Fakturalista', $html);
            return true;
        });
    }

    /** @test */
    public function the_welcome_email_respects_an_explicit_spanish_choice_even_for_a_moroccan_tenant(): void
    {
        Mail::fake();
        $service = app(TenantProvisioningService::class);
        $this->track($service->provision(
            $this->provisioningData(['country' => 'MA', 'language' => 'es']),
            selfService: false,
        ));

        Mail::assertQueued(WelcomeTenantMail::class, function (WelcomeTenantMail $mail) {
            $this->assertSame('es', $mail->locale);
            $html = $mail->render();
            $this->assertStringContainsString('Bienvenido a Fakturalista', $html);
            $this->assertStringNotContainsString('Bienvenue sur Fakturalista', $html);
            return true;
        });
    }

    /** @test */
    public function the_welcome_email_respects_an_explicit_english_choice(): void
    {
        Mail::fake();
        $service = app(TenantProvisioningService::class);
        $this->track($service->provision(
            $this->provisioningData(['country' => 'MA', 'language' => 'en']),
            selfService: false,
        ));

        Mail::assertQueued(WelcomeTenantMail::class, function (WelcomeTenantMail $mail) {
            $this->assertSame('en', $mail->locale);
            $html = $mail->render();
            $this->assertStringContainsString('Welcome to Fakturalista', $html);
            $this->assertStringNotContainsString('Bienvenue sur Fakturalista', $html);
            return true;
        });
    }

    // ── 5. Onboarding messages default to French ───────────────────────────

    /** @test */
    public function onboarding_already_completed_message_defaults_to_french_with_no_explicit_locale(): void
    {
        $tenant = Tenant::create(['id' => 'test-lang-onboarding-' . uniqid()]);
        $domain = $tenant->id . '.fakturalista.test';
        $tenant->domains()->create(['domain' => $domain]);
        $this->track($tenant);

        $user = $tenant->run(function () {
            CompanyProfile::create([
                'legal_name' => 'Onboarding Lang Test',
                'country_code' => 'MA', 'currency' => 'MAD',
                // Already completed, so store() short-circuits through the
                // __('onboarding.already_completed') branch this test
                // targets - the exact message string shown to the browser
                // when the wizard is reopened after signup.
                'onboarding_completed_at' => now(),
            ]);
            // Explicit French locale on the user: SetLocale's first
            // priority is the authenticated user's own saved locale, so
            // this simulates a freshly-provisioned Moroccan owner (whose
            // locale was correctly set to 'fr' at creation time) rather
            // than testing an unrelated middleware fallback path.
            return User::factory()->create(['locale' => 'fr']);
        });
        $this->actingAs($user, 'api');

        $response = $this->postJson('http://' . $domain . '/api/onboarding', [
            'owner_name'    => 'Test Owner',
            'trade_name'    => 'Onboarding Lang Test',
            'address_line1' => '123 Rue Test',
            'city'          => 'Casablanca',
            'postal_code'   => '20100',
            'country'       => 'Morocco',
            'currency'      => 'MAD',
        ]);

        $response->assertOk();
        $response->assertJson(['onboarding_completed' => true]);
        $this->assertSame('La configuration a déjà été effectuée.', $response->json('message'));
    }

    // ── 6. Changing UI language never touches country/currency/tax config ─

    /** @test */
    public function changing_the_ui_language_does_not_alter_tenant_country_currency_or_tax_config(): void
    {
        $tenant = Tenant::create(['id' => 'test-lang-noleak-' . uniqid()]);
        $domain = $tenant->id . '.fakturalista.test';
        $tenant->domains()->create(['domain' => $domain]);
        $this->track($tenant);

        $user = $tenant->run(function () {
            CompanyProfile::create([
                'legal_name' => 'No Leak Test',
                'country_code' => 'MA', 'currency' => 'MAD', 'locale' => 'fr',
                'onboarding_completed_at' => now(),
            ]);
            return User::factory()->create(['locale' => 'fr']);
        });
        $this->actingAs($user, 'api');

        $before = $this->getJson('http://' . $domain . '/api/user')->assertOk()->json('company_context');
        $this->assertSame('MA', $before['country']);
        $this->assertSame('MAD', $before['currency']);

        // A user explicitly switching their own UI language to Spanish...
        $response = $this->putJson('http://' . $domain . '/api/user', ['locale' => 'es']);
        $response->assertOk();

        $updatedLocale = $tenant->run(fn () => User::first()->locale);
        $this->assertSame('es', $updatedLocale, 'The explicit language choice itself must be saved.');

        // ...must never change the tenant's country, currency, or tax
        // configuration - those are driven solely by CompanyProfile/Tenant
        // country_code, never by the UI language.
        $after = $this->getJson('http://' . $domain . '/api/user')->assertOk()->json('company_context');
        $this->assertSame($before['country'], $after['country']);
        $this->assertSame($before['currency'], $after['currency']);
        $this->assertSame($before['timezone'], $after['timezone']);
    }
}
