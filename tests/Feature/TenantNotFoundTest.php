<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantDomainVisit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Tests for the "tenant not found" flow.
 *
 * These tests do NOT use RefreshDatabase because this project uses Stancl
 * Tenancy's multi-database setup, which does not play well with migrate:fresh.
 * Instead each test cleans up its own records.
 *
 * Run with:
 *   php artisan test --filter=TenantNotFoundTest
 */
class TenantNotFoundTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Clean slate: remove any visit records left by a previous run.
        DB::table('tenant_domain_visits')->where('domain', 'like', '%-tnf-test.%')->delete();
    }

    protected function tearDown(): void
    {
        DB::table('tenant_domain_visits')->where('domain', 'like', '%-tnf-test.%')->delete();
        parent::tearDown();
    }

    // ── 1. Unknown subdomain → redirect ──────────────────────────────────

    public function test_unknown_tenant_subdomain_redirects_to_not_found_page(): void
    {
        $response = $this->get('http://missing-tnf-test.fakturalista.test/');

        // Should be a redirect (302) to the central domain's not-found page
        $response->assertRedirect();
        $location = $response->headers->get('Location');

        $this->assertStringContainsString('/tenant-not-found', $location);
        $this->assertStringContainsString('domain=', $location);
        $this->assertStringContainsString('missing-tnf-test.fakturalista.test', $location);
    }

    // ── 2. Not-found page records the visit in the central DB ─────────────

    public function test_unknown_tenant_subdomain_is_recorded_in_database(): void
    {
        $this->get('http://missing-tnf-test.fakturalista.test/');

        $this->assertDatabaseHas('tenant_domain_visits', [
            'domain'    => 'missing-tnf-test.fakturalista.test',
            'subdomain' => 'missing-tnf-test',
        ]);
    }

    // ── 3. The /tenant-not-found page renders correctly ───────────────────

    public function test_tenant_not_found_page_renders_with_domain(): void
    {
        $domain = 'walid-tnf-test.fakturalista.test';

        $response = $this->get(
            'http://fakturalista.test/tenant-not-found?domain=' . urlencode($domain)
        );

        $response->assertOk();
        $response->assertViewIs('errors.tenant-not-found');
        $response->assertSee('Este espacio de cliente no existe');
        $response->assertSee($domain);
        $response->assertSee('Crear cuenta gratis');
    }

    // ── 4. The /tenant-not-found page works without a domain query param ──

    public function test_tenant_not_found_page_renders_without_domain(): void
    {
        $response = $this->get('http://fakturalista.test/tenant-not-found');

        $response->assertOk();
        $response->assertViewIs('errors.tenant-not-found');
        $response->assertSee('Este espacio de cliente no existe');
    }

    // ── 5. Existing tenant domain works normally ──────────────────────────

    public function test_existing_tenant_domain_is_not_redirected(): void
    {
        // Create a real tenant with a known domain so the middleware finds it.
        $tenant = Tenant::create(['id' => 'tnf-existing-' . uniqid()]);
        $domain = 'tnf-existing-test.fakturalista.test';
        $tenant->domains()->create(['domain' => $domain]);

        try {
            // The middleware WILL find this tenant — no redirect should occur.
            // We check that it does NOT redirect to /tenant-not-found.
            $response = $this->get("http://{$domain}/");

            // The tenant app serves its own routes; what matters is it's not
            // our not-found redirect (302 → /tenant-not-found).
            if ($response->isRedirect()) {
                $this->assertStringNotContainsString(
                    'tenant-not-found',
                    $response->headers->get('Location', '')
                );
            }

            // Third arg pins the assertion to the central connection so it doesn't
            // run against the tenant DB that was initialized by the HTTP middleware.
            $this->assertDatabaseMissing('tenant_domain_visits', ['domain' => $domain], 'mysql');
        } finally {
            tenancy()->end();
            $tenant->delete();
        }
    }

    // ── 6. Central domain (marketing site) is unaffected ─────────────────

    public function test_central_domain_home_page_works(): void
    {
        $response = $this->get('http://fakturalista.test/');
        $response->assertOk();
    }

    // ── 7. Backoffice is unaffected ───────────────────────────────────────

    public function test_backoffice_login_is_accessible(): void
    {
        // The backoffice runs on the central domain; InitializeTenancyByDomain
        // is never triggered for these requests, so $onFail must not fire.
        $response = $this->get('http://fakturalista.test/backoffice/login');

        // 200 (login form) or 302 (already authenticated) — anything but our redirect
        $this->assertNotEquals(404, $response->status());
        if ($response->isRedirect()) {
            $this->assertStringNotContainsString(
                'tenant-not-found',
                $response->headers->get('Location', '')
            );
        }
    }

    // ── 8. API clients receive JSON, not a redirect ───────────────────────

    public function test_api_client_on_unknown_tenant_receives_json_404(): void
    {
        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->get('http://missing-tnf-test.fakturalista.test/api/user');

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Tenant could not be identified on domain missing-tnf-test.fakturalista.test']);
    }
}
