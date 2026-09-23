<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Tenant "Utilisateurs" (team management) feature: strictly isolated per
 * tenant, seat limit enforced via the existing Plan/PlanLimit 'users'
 * resource (PlanService) - never a second hardcoded limit. Requires a
 * real tenant database, same pattern as InvoiceLifecycleTest.
 */
class UserManagementTest extends TestCase
{
    protected Tenant $tenant;
    protected User   $owner;
    protected string $domain;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['id' => 'test-team-' . uniqid()]);
        $this->domain = 'test-team-' . uniqid() . '.fakturalista.test';
        $this->tenant->domains()->create(['domain' => $this->domain]);
        $this->tenant->update(['owner_email' => 'owner@example.com']);

        tenancy()->initialize($this->tenant);

        // The owner user - email matches tenants.owner_email, so the
        // 2026_09_23_000000_add_role_to_users_table.php backfill logic
        // (and User::isOwner()) recognize them as the account owner.
        $this->owner = User::factory()->create(['email' => 'owner@example.com', 'role' => 'admin']);

        CompanyProfile::create([
            'legal_name'              => 'Test Team Co',
            'tax_id'                  => 'B12345678',
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

    private function subscribeTo(string $slug): void
    {
        $plan = Plan::on('mysql')->where('slug', $slug)->first();

        Subscription::create([
            'tenant_id'  => $this->tenant->getTenantKey(),
            'plan_id'    => $plan->id,
            'provider'   => 'stripe',
            'status'     => 'active',
        ]);
    }

    // ── Listing + usage ──────────────────────────────────────────────

    /** @test */
    public function the_owner_is_admin_and_counts_as_one_user(): void
    {
        $this->actingAs($this->owner, 'api');

        $response = $this->getJson($this->apiUrl('/api/users'));

        $response->assertOk();
        $response->assertJsonCount(1, 'users');
        $response->assertJsonPath('users.0.role', 'admin');
        $response->assertJsonPath('users.0.is_owner', true);
        $response->assertJsonPath('usage.used', 1);
        $response->assertJsonPath('usage.limit', 1); // Starter fallback plan
        $response->assertJsonPath('can_manage', true);
    }

    // ── Plan-limit enforcement (reused PlanService, no new hardcoded limit) ──

    /** @test */
    public function starter_plan_blocks_a_second_user_since_owner_already_fills_the_one_seat(): void
    {
        $this->actingAs($this->owner, 'api');

        $response = $this->postJson($this->apiUrl('/api/users'), [
            'name' => 'New Teammate', 'email' => 'teammate@example.com',
            'password' => 'password123', 'role' => 'member',
        ]);

        $response->assertStatus(402);
        $response->assertJsonPath('error', 'plan_limit_reached');
        $response->assertJsonPath('resource', 'users');
        $response->assertJsonPath('limit', 1);
        $this->assertSame(1, User::count());
    }

    /** @test */
    public function upgrading_to_pro_immediately_allows_up_to_five_users_without_any_code_change(): void
    {
        $this->subscribeTo('pro');
        $this->actingAs($this->owner, 'api');

        for ($i = 1; $i <= 4; $i++) {
            $response = $this->postJson($this->apiUrl('/api/users'), [
                'name' => "Teammate {$i}", 'email' => "teammate{$i}@example.com",
                'password' => 'password123', 'role' => 'member',
            ]);
            $response->assertStatus(201);
        }
        $this->assertSame(5, User::count());

        // 6th user (owner + 5 members) exceeds Pro's limit of 5.
        $response = $this->postJson($this->apiUrl('/api/users'), [
            'name' => 'One Too Many', 'email' => 'toomany@example.com',
            'password' => 'password123', 'role' => 'member',
        ]);
        $response->assertStatus(402);
        $response->assertJsonPath('limit', 5);
        $this->assertSame(5, User::count());
    }

    /** @test */
    public function business_plan_has_no_user_limit(): void
    {
        $this->subscribeTo('business');
        $this->actingAs($this->owner, 'api');

        for ($i = 1; $i <= 8; $i++) {
            $this->postJson($this->apiUrl('/api/users'), [
                'name' => "Teammate {$i}", 'email' => "teammate{$i}@example.com",
                'password' => 'password123', 'role' => 'member',
            ])->assertStatus(201);
        }
        $this->assertSame(9, User::count());

        $response = $this->getJson($this->apiUrl('/api/users'));
        $response->assertJsonPath('usage.limit', null);
    }

    // ── Role-based access ─────────────────────────────────────────────

    /** @test */
    public function a_member_can_view_the_list_but_cannot_manage_users(): void
    {
        $member = User::create([
            'name' => 'Regular Member', 'email' => 'member@example.com',
            'password' => Hash::make('password123'), 'role' => 'member',
        ]);
        $this->actingAs($member, 'api');

        $this->getJson($this->apiUrl('/api/users'))
            ->assertOk()
            ->assertJsonPath('can_manage', false);

        $this->postJson($this->apiUrl('/api/users'), [
            'name' => 'Blocked', 'email' => 'blocked@example.com',
            'password' => 'password123', 'role' => 'member',
        ])->assertStatus(403);

        $this->deleteJson($this->apiUrl('/api/users/' . $this->owner->id))
            ->assertStatus(403);
    }

    // ── Owner protection ──────────────────────────────────────────────

    /** @test */
    public function the_owner_cannot_be_deleted_or_demoted(): void
    {
        $this->actingAs($this->owner, 'api');

        $this->deleteJson($this->apiUrl('/api/users/' . $this->owner->id))
            ->assertStatus(403);
        $this->assertSame(1, User::count());

        $this->putJson($this->apiUrl('/api/users/' . $this->owner->id), [
            'name' => $this->owner->name, 'email' => $this->owner->email, 'role' => 'member',
        ])->assertStatus(403);
        $this->assertSame('admin', $this->owner->fresh()->role);
    }

    /** @test */
    public function a_non_owner_admin_cannot_delete_themselves(): void
    {
        $this->subscribeTo('pro');
        $admin2 = User::create([
            'name' => 'Second Admin', 'email' => 'admin2@example.com',
            'password' => Hash::make('password123'), 'role' => 'admin',
        ]);
        $this->actingAs($admin2, 'api');

        $this->deleteJson($this->apiUrl('/api/users/' . $admin2->id))
            ->assertStatus(403);
        $this->assertSame(2, User::count());
    }

    /** @test */
    public function an_admin_can_edit_and_remove_a_regular_member(): void
    {
        $this->subscribeTo('pro');
        $this->actingAs($this->owner, 'api');

        $this->postJson($this->apiUrl('/api/users'), [
            'name' => 'Temp Member', 'email' => 'temp@example.com',
            'password' => 'password123', 'role' => 'member',
        ])->assertStatus(201);
        $member = User::where('email', 'temp@example.com')->first();

        $this->putJson($this->apiUrl('/api/users/' . $member->id), [
            'name' => 'Renamed Member', 'email' => 'temp@example.com', 'role' => 'admin',
        ])->assertOk();
        $this->assertSame('admin', $member->fresh()->role);
        $this->assertSame('Renamed Member', $member->fresh()->name);

        $this->deleteJson($this->apiUrl('/api/users/' . $member->id))->assertOk();
        $this->assertSame(1, User::count());
    }

    // ── Real self-service signup must produce an admin owner ──────────

    /** @test */
    public function a_brand_new_self_service_signup_makes_its_owner_an_admin(): void
    {
        // Runs outside $this->tenant - a real end-to-end signup through
        // the public /register flow (RegisterTrialController), the same
        // path every new Fakturalista customer goes through. Guards
        // against the role column's own migration-time backfill (which
        // only helps pre-existing tenants) being mistaken for the whole
        // fix - TenantProvisioningService::provision() must itself set
        // 'role' => 'admin' when creating a brand-new owner.
        tenancy()->end();

        $this->get('http://fakturalista.test/register');
        $captchaAnswer = session('math_captcha_answer');
        $email = 'newowner+' . uniqid() . '@example.com';

        $this->post('http://fakturalista.test/register', [
            'name'           => 'Brand New Owner',
            'email'          => $email,
            'password'       => 'SecurePass123',
            'captcha_answer' => $captchaAnswer,
        ])->assertRedirect();

        $newTenant = Tenant::where('owner_email', $email)->firstOrFail();
        $newTenant->run(function () {
            $owner = User::first();
            $this->assertSame('admin', $owner->role);
        });

        $newTenant->delete();
        tenancy()->initialize($this->tenant);
    }

    // ── Tenant isolation ──────────────────────────────────────────────

    /** @test */
    public function users_are_strictly_isolated_between_tenants(): void
    {
        tenancy()->end();

        $otherTenant = Tenant::create(['id' => 'test-team-other-' . uniqid()]);
        $otherDomain = 'test-team-other-' . uniqid() . '.fakturalista.test';
        $otherTenant->domains()->create(['domain' => $otherDomain]);
        $otherTenant->update(['owner_email' => 'other-owner@example.com']);

        tenancy()->initialize($otherTenant);
        $otherOwner = User::factory()->create(['email' => 'other-owner@example.com', 'role' => 'admin']);
        CompanyProfile::create(['legal_name' => 'Other Co', 'onboarding_completed_at' => now()]);

        $this->actingAs($otherOwner, 'api');
        $response = $this->getJson('http://' . $otherDomain . '/api/users');
        $response->assertOk();
        $response->assertJsonCount(1, 'users');
        $response->assertJsonPath('users.0.email', 'other-owner@example.com');

        $otherTenant->delete();

        tenancy()->initialize($this->tenant);
        $this->assertSame(1, User::count());
        $this->assertSame('owner@example.com', User::first()->email);
    }
}
