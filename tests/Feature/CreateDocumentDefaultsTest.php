<?php

namespace Tests\Feature;

use App\Models\{CompanyProfile, Customer, Invoice, Quote, Tenant, User};
use Tests\TestCase;

/**
 * Smart defaults on NEW invoices/quotes. The create forms take their
 * defaults from the workspace via existing endpoints - the default tax from
 * GET /api/tax-presets (default_code), the currency from the company
 * context (GET /api/user) - and the backend stays authoritative. These
 * tests pin that those defaults follow the workspace configuration, and
 * that creating documents works with and without configured defaults.
 */
class CreateDocumentDefaultsTest extends TestCase
{
    private array $tenants = [];

    protected function tearDown(): void
    {
        tenancy()->end();
        foreach ($this->tenants as $tenant) {
            $tenant->delete();
        }
        parent::tearDown();
    }

    /** @return array{0: Tenant, 1: string, 2: string} tenant, domain, customer uuid */
    private function workspace(array $tenantAttrs, array $profile): array
    {
        $tenant = Tenant::create(['id' => 'test-defaults-' . uniqid()] + $tenantAttrs);
        $this->tenants[] = $tenant;
        $domain = $tenant->id . '.fakturalista.test';
        $tenant->domains()->create(['domain' => $domain]);

        [$user, $customerUuid] = $tenant->run(function () use ($profile) {
            CompanyProfile::create($profile + [
                'legal_name' => 'Defaults Co', 'invoice_prefix' => 'INV', 'onboarding_completed_at' => now(),
            ]);
            return [User::factory()->create(), Customer::factory()->create(['type' => 1])->uuid];
        });
        $this->actingAs($user, 'api');
        // Reused app instance across requests: start each workspace on the API guard cleanly.
        $this->app['auth']->forgetGuards();
        $this->actingAs($user, 'api');

        return [$tenant, $domain, $customerUuid];
    }

    private function api(string $domain, string $path): string
    {
        return 'http://' . $domain . '/api/' . $path;
    }

    /** What the create form sends for one line carrying the default tax it was given. */
    private function create(string $domain, string $type, string $customerUuid, array $tax): void
    {
        $this->postJson($this->api($domain, $type), [
            'customer_id' => $customerUuid,
            'date' => now()->toDateString(), 'expiration_date' => now()->addDays(30)->toDateString(),
            'status' => 'draft', 'discount_rate' => 0,
            'carts' => [[
                'item_id' => '', 'description' => 'Service', 'qty' => 1, 'unite' => 'pc',
                'price' => 1000, 'discount' => 0, 'vta' => $tax['rate'], 'tax_treatment' => $tax['treatment'], 'total' => 1000,
            ]],
        ])->assertOk();
    }

    private function defaultPreset(string $domain): array
    {
        $data = $this->getJson($this->api($domain, 'tax-presets'))->assertOk()->json();
        $this->assertNotNull($data['default_code'], 'A new document always gets a default tax.');

        return collect($data['presets'])->firstWhere('code', $data['default_code']);
    }

    public function test_configured_defaults_are_used_for_new_invoices_and_quotes(): void
    {
        [$tenant, $domain, $customer] = $this->workspace(
            ['country' => 'MA', 'currency' => 'MAD'],
            ['country_code' => 'MA', 'currency' => 'MAD', 'locale' => 'fr']
        );

        // Configure a non-standard default tax (any other taxable preset).
        $presets = $this->getJson($this->api($domain, 'tax-presets'))->json();
        $other = collect($presets['presets'])->first(fn ($p) => $p['code'] !== $presets['default_code'] && $p['treatment'] === 'taxable' && $p['rate'] > 0);
        $tenant->run(fn () => CompanyProfile::first()->update(['default_tax_code' => $other['code']]));

        $default = $this->defaultPreset($domain);
        $this->assertSame($other['code'], $default['code'], 'The configured default tax is what new lines get.');
        $this->assertSame('MAD', $this->getJson($this->api($domain, 'user'))->json('company_context.currency'));

        $this->create($domain, 'invoices', $customer, $default);
        $this->create($domain, 'quotes', $customer, $default);

        $expected = 1000 * (1 + $default['rate'] / 100);
        $this->assertEquals($expected, (float) $tenant->run(fn () => Invoice::latest('id')->first()->total));
        $this->assertEquals($expected, (float) $tenant->run(fn () => Quote::latest('id')->first()->total));
    }

    public function test_without_optional_defaults_documents_fall_back_to_existing_behaviour(): void
    {
        // No default tax chosen (the only optional default - the company
        // currency is a required column, always set at provisioning).
        [$tenant, $domain, $customer] = $this->workspace(
            ['country' => 'MA', 'currency' => 'MAD'],
            ['country_code' => 'MA', 'currency' => 'MAD', 'locale' => 'fr', 'default_tax_code' => null]
        );

        $default = $this->defaultPreset($domain); // country default, as before
        $this->assertSame('MAD', $this->getJson($this->api($domain, 'user'))->json('company_context.currency'));

        $this->create($domain, 'invoices', $customer, $default);
        $this->create($domain, 'quotes', $customer, $default);

        $this->assertSame(1, $tenant->run(fn () => Invoice::count()));
        $this->assertSame(1, $tenant->run(fn () => Quote::count()));
    }

    public function test_a_different_currency_and_tax_rate_follow_the_workspace(): void
    {
        [$tenantEs, $domainEs, $customerEs] = $this->workspace(
            ['country' => 'ES', 'currency' => 'EUR'],
            ['country_code' => 'ES', 'currency' => 'EUR', 'locale' => 'es']
        );
        $es = $this->defaultPreset($domainEs);
        $this->assertSame('EUR', $this->getJson($this->api($domainEs, 'user'))->json('company_context.currency'));
        $this->create($domainEs, 'invoices', $customerEs, $es);
        $this->create($domainEs, 'quotes', $customerEs, $es);

        [$tenantMa, $domainMa] = $this->workspace(
            ['country' => 'MA', 'currency' => 'MAD'],
            ['country_code' => 'MA', 'currency' => 'MAD', 'locale' => 'fr']
        );
        $ma = $this->defaultPreset($domainMa);

        // Each workspace gets its own country's default, never the other's.
        $this->assertNotSame($es['code'], $ma['code']);
        $this->assertEquals(1000 * (1 + $es['rate'] / 100), (float) $tenantEs->run(fn () => Invoice::latest('id')->first()->total));
    }
}
