<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\CustomerPortalAccess;
use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;

/**
 * Admin management of a customer's Client Portal link (customer edit page)
 * - CustomerPortalAccessController on top of ClientPortalService.
 */
class CustomerPortalAccessAdminTest extends TestCase
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

    /** @return array{0: Tenant, 1: string, 2: User, 3: Customer} */
    private function makeTenant(): array
    {
        $tenant = Tenant::create(['id' => 'test-portal-admin-' . uniqid()]);
        $domain = $tenant->id . '.fakturalista.test';
        $tenant->domains()->create(['domain' => $domain]);
        $this->tenants[] = $tenant;

        [$user, $customer] = $tenant->run(function () {
            CompanyProfile::create([
                'legal_name' => 'Portal Admin Co', 'country_code' => 'MA', 'currency' => 'MAD',
                'invoice_prefix' => 'INV', 'onboarding_completed_at' => now(),
            ]);

            return [User::factory()->create(), Customer::factory()->create(['type' => 1])];
        });

        return [$tenant, $domain, $user, $customer];
    }

    private function url(string $domain, Customer $customer, string $suffix = ''): string
    {
        return 'http://' . $domain . '/api/customers/' . $customer->uuid . '/portal-access' . $suffix;
    }

    private function tokenFrom(string $portalUrl): string
    {
        $this->assertMatchesRegularExpression('~/portal/[A-Za-z0-9]{64}$~', $portalUrl);

        return substr($portalUrl, strrpos($portalUrl, '/') + 1);
    }

    private function portal(string $domain, string $token)
    {
        return $this->getJson('http://' . $domain . '/api/portal/' . $token);
    }

    private function activeRows(Tenant $tenant, Customer $customer): int
    {
        return $tenant->run(fn () => CustomerPortalAccess::where('customer_id', $customer->id)->whereNull('revoked_at')->count());
    }

    public function test_creating_portal_access_returns_the_link_once_and_stores_only_a_hash(): void
    {
        [$tenant, $domain, $user, $customer] = $this->makeTenant();
        $this->actingAs($user, 'api');

        $this->getJson($this->url($domain, $customer))->assertOk()->assertExactJson([
            'active' => false, 'created_at' => null, 'last_accessed_at' => null,
        ]);

        $created = $this->postJson($this->url($domain, $customer))->assertCreated();
        $this->assertStringContainsString('no-store', $created->headers->get('Cache-Control'));
        $this->assertStringStartsWith('http://' . $domain . '/portal/', $created->json('url'));
        $token = $this->tokenFrom($created->json('url'));
        $this->assertTrue($created->json('active'));

        // Only the SHA-256 hash is stored - never the raw token.
        $rows = $tenant->run(fn () => CustomerPortalAccess::where('customer_id', $customer->id)->get()->toArray());
        $this->assertCount(1, $rows);
        $this->assertSame(hash('sha256', $token), $rows[0]['token_hash']);
        $this->assertStringNotContainsString($token, json_encode($rows));

        // The link works...
        $this->portal($domain, $token)->assertOk();

        // ...and is never returned again.
        $status = $this->getJson($this->url($domain, $customer))->assertOk();
        $this->assertSame(['active', 'created_at', 'last_accessed_at'], array_keys($status->json()));
        $this->assertTrue($status->json('active'));
        $this->assertNotNull($status->json('last_accessed_at'));
        $this->assertStringNotContainsString($token, $status->getContent());
        $this->assertStringNotContainsString(hash('sha256', $token), $status->getContent());

        // A second "create" doesn't silently mint another live link.
        $this->postJson($this->url($domain, $customer))->assertStatus(409);
        $this->assertSame(1, $this->activeRows($tenant, $customer));
    }

    public function test_regenerating_revokes_the_previous_link(): void
    {
        [$tenant, $domain, $user, $customer] = $this->makeTenant();
        $this->actingAs($user, 'api');

        $old = $this->tokenFrom($this->postJson($this->url($domain, $customer))->json('url'));
        $new = $this->tokenFrom($this->postJson($this->url($domain, $customer, '/regenerate'))->assertOk()->json('url'));

        $this->assertNotSame($old, $new);
        $this->portal($domain, $old)->assertStatus(410);
        $this->portal($domain, $new)->assertOk();
        $this->assertSame(1, $this->activeRows($tenant, $customer));
    }

    public function test_revoking_makes_the_existing_link_unusable(): void
    {
        [$tenant, $domain, $user, $customer] = $this->makeTenant();
        $this->actingAs($user, 'api');

        $token = $this->tokenFrom($this->postJson($this->url($domain, $customer))->json('url'));
        $this->portal($domain, $token)->assertOk();

        $this->deleteJson($this->url($domain, $customer))->assertOk()->assertJsonPath('active', false);

        $this->portal($domain, $token)->assertStatus(410);
        $this->assertSame(0, $this->activeRows($tenant, $customer));

        // A fresh link can be created afterwards; the revoked one stays dead.
        $fresh = $this->tokenFrom($this->postJson($this->url($domain, $customer))->assertCreated()->json('url'));
        $this->portal($domain, $fresh)->assertOk();
        $this->portal($domain, $token)->assertStatus(410);
    }

    public function test_tenants_and_customers_are_isolated(): void
    {
        [$tenantA, $domainA, $userA, $customerA] = $this->makeTenant();
        [$tenantB, $domainB, $userB, $customerB] = $this->makeTenant();

        // Tenant A's admin can't see or manage tenant B's customer.
        $this->actingAs($userA, 'api');
        foreach (['get', 'post', 'delete'] as $method) {
            $this->json($method, $this->url($domainA, $customerB))->assertNotFound();
        }
        $this->postJson($this->url($domainA, $customerB, '/regenerate'))->assertNotFound();
        $this->assertSame(0, $this->activeRows($tenantB, $customerB));

        // A link made for tenant B's customer only works on tenant B's domain.
        $this->actingAs($userB, 'api');
        $tokenB = $this->tokenFrom($this->postJson($this->url($domainB, $customerB))->json('url'));
        $this->portal($domainA, $tokenB)->assertNotFound();
        $this->portal($domainB, $tokenB)->assertOk();

        // Managing one customer never touches another customer's link.
        $otherB = $tenantB->run(fn () => Customer::factory()->create(['type' => 1]));
        $this->deleteJson($this->url($domainB, $otherB))->assertOk();
        $this->portal($domainB, $tokenB)->assertOk();
    }

    public function test_portal_access_management_requires_authentication(): void
    {
        [, $domain, , $customer] = $this->makeTenant();

        $this->getJson($this->url($domain, $customer))->assertUnauthorized();
        $this->postJson($this->url($domain, $customer))->assertUnauthorized();
        $this->postJson($this->url($domain, $customer, '/regenerate'))->assertUnauthorized();
        $this->deleteJson($this->url($domain, $customer))->assertUnauthorized();
    }
}
