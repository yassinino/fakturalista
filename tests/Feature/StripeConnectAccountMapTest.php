<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\StripeConnectAccount;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StripeConnectService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeStripe;
use Tests\TestCase;

/**
 * Step 6C - the central Stripe Connect account -> tenant map: linking,
 * one-account-one-workspace conflicts, the backfill command and the
 * readiness check. Offline (FakeStripe + Http::fake); the commands are
 * always scoped with --tenant so they never touch other tenants living
 * in the same local central database.
 */
class StripeConnectAccountMapTest extends TestCase
{
    private FakeStripe $stripe;
    private array $tenants = [];

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.stripe.secret'                 => 'sk_test_offline_fake',
            'services.stripe.webhook_secret'         => 'whsec_test_offline_platform',
            'services.stripe.connect_webhook_secret' => 'whsec_test_offline_connect',
            'services.stripe.connect_client_id'      => 'ca_test_offline',
        ]);
        $this->stripe = FakeStripe::install();
        $this->stripe->accounts = [
            'acct_map_A' => ['details_submitted' => true, 'charges_enabled' => true, 'payouts_enabled' => true],
            'acct_map_B' => ['details_submitted' => true, 'charges_enabled' => true, 'payouts_enabled' => true],
        ];
    }

    protected function tearDown(): void
    {
        FakeStripe::uninstall();
        tenancy()->end();
        foreach ($this->tenants as $tenant) {
            $tenant->delete();
        }
        parent::tearDown();
    }

    private function makeTenant(?string $account = null): Tenant
    {
        $tenant = Tenant::create(['id' => 'test-map-' . uniqid()]);
        $this->tenants[] = $tenant;

        $tenant->run(function () use ($account) {
            User::factory()->create();
            CompanyProfile::create([
                'legal_name' => 'Map Co', 'country_code' => 'MA', 'currency' => 'MAD', 'invoice_prefix' => 'INV',
                'onboarding_completed_at' => now(),
                'stripe_account_id' => $account, 'onboarding_completed' => (bool) $account, 'charges_enabled' => (bool) $account,
            ]);
        });

        return $tenant;
    }

    private function mappedTenant(string $account): ?string
    {
        return StripeConnectAccount::where('stripe_account_id', $account)->value('tenant_id');
    }

    private function profileAccount(Tenant $tenant): ?string
    {
        return $tenant->run(fn () => CompanyProfile::first()->stripe_account_id);
    }

    /** Simulates the OAuth callback for $account inside $tenant. */
    private function link(Tenant $tenant, string $account): array
    {
        Http::fake(['connect.stripe.com/oauth/token' => Http::response(['stripe_user_id' => $account])]);

        return $tenant->run(fn () => app(StripeConnectService::class)->handleCallback('ac_test_code'));
    }

    private function backfill(array $tenants, array $extra = []): array
    {
        $code = Artisan::call('stripe:backfill-connect-accounts', $extra + [
            '--tenant' => array_map(fn (Tenant $t) => $t->getTenantKey(), $tenants),
        ]);

        return [$code, Artisan::output()];
    }

    // ── Linking ─────────────────────────────────────────────────────────

    public function test_linking_a_connect_account_still_works_and_maps_it(): void
    {
        $tenant = $this->makeTenant();

        $result = $this->link($tenant, 'acct_map_A');

        $this->assertSame('acct_map_A', $result['stripe_account_id']);
        $this->assertSame('acct_map_A', $this->profileAccount($tenant));
        $this->assertTrue((bool) $tenant->run(fn () => CompanyProfile::first()->charges_enabled));
        $this->assertSame($tenant->getTenantKey(), $this->mappedTenant('acct_map_A'));
    }

    public function test_an_account_still_used_by_another_workspace_is_never_moved(): void
    {
        $owner    = $this->makeTenant();
        $intruder = $this->makeTenant('acct_map_B');
        $this->link($owner, 'acct_map_A');
        $intruder->run(fn () => app(StripeConnectService::class)->syncCentralMapping(CompanyProfile::first()));

        try {
            $this->link($intruder, 'acct_map_A');
            $this->fail('Linking an account owned by another workspace must be refused.');
        } catch (\RuntimeException $e) {
            $this->assertSame(StripeConnectService::ACCOUNT_IN_USE_MESSAGE, $e->getMessage());
            // The owner lookup ran in the OWNER's tenant and then handed the
            // request back to the linking workspace (not left in the owner's DB).
            $this->assertSame($intruder->getTenantKey(), tenant()?->getTenantKey());
            tenancy()->end(); // the test helper's run() doesn't restore on exceptions
        }

        // Nothing moved: owner keeps the mapping, the intruder keeps its own account.
        $this->assertSame($owner->getTenantKey(), $this->mappedTenant('acct_map_A'));
        $this->assertSame('acct_map_B', $this->profileAccount($intruder));
        $this->assertSame($intruder->getTenantKey(), $this->mappedTenant('acct_map_B'));
        $this->assertNull(tenant(), 'Tenancy is back to central after the refusal.');
    }

    public function test_a_stale_mapping_is_released_once_its_old_tenant_no_longer_uses_the_account(): void
    {
        $old = $this->makeTenant();
        $new = $this->makeTenant();
        $this->link($old, 'acct_map_A');

        // The old workspace switched to another account without the map being re-synced.
        $old->run(fn () => CompanyProfile::first()->update(['stripe_account_id' => 'acct_map_B']));

        $this->link($new, 'acct_map_A');

        $this->assertSame($new->getTenantKey(), $this->mappedTenant('acct_map_A'));
    }

    // ── Backfill ────────────────────────────────────────────────────────

    public function test_backfill_creates_missing_mappings_and_skips_tenants_without_stripe(): void
    {
        $connected = $this->makeTenant('acct_map_A');   // linked before the map existed: no row
        $none      = $this->makeTenant();
        $this->assertNull($this->mappedTenant('acct_map_A'));

        [$code, $output] = $this->backfill([$connected, $none]);

        $this->assertSame(0, $code, $output);
        $this->assertSame($connected->getTenantKey(), $this->mappedTenant('acct_map_A'));
        $this->assertFalse(StripeConnectAccount::where('tenant_id', $none->getTenantKey())->exists());
        $this->assertMatchesRegularExpression('/created\s+1/', $output);
        $this->assertMatchesRegularExpression('/skipped\s+1/', $output);
    }

    public function test_backfill_is_idempotent_and_dry_run_writes_nothing(): void
    {
        $tenant = $this->makeTenant('acct_map_A');

        [, $dry] = $this->backfill([$tenant], ['--dry-run' => true]);
        $this->assertStringContainsString('dry run', $dry);
        $this->assertNull($this->mappedTenant('acct_map_A'), 'Dry run must not write.');

        $this->backfill([$tenant]);
        [$code, $second] = $this->backfill([$tenant]);

        $this->assertSame(0, $code);
        $this->assertMatchesRegularExpression('/unchanged\s+1/', $second);
        $this->assertMatchesRegularExpression('/created\s+0/', $second);
        $this->assertSame(1, StripeConnectAccount::where('stripe_account_id', 'acct_map_A')->count());
    }

    public function test_backfill_reports_a_conflict_and_never_overwrites_the_existing_mapping(): void
    {
        $owner = $this->makeTenant('acct_map_A');
        $this->backfill([$owner]);

        // A second workspace whose own profile also holds the same account.
        $duplicate = $this->makeTenant('acct_map_A');

        [$code, $output] = $this->backfill([$owner, $duplicate]);

        $this->assertSame(1, $code, 'Conflicts make the command fail loudly.');
        $this->assertStringContainsString($duplicate->getTenantKey(), $output);
        $this->assertMatchesRegularExpression('/conflict\s+1/', $output);
        $this->assertSame($owner->getTenantKey(), $this->mappedTenant('acct_map_A'));
    }

    // ── Readiness check ─────────────────────────────────────────────────

    public function test_readiness_check_reports_status_without_ever_printing_secrets(): void
    {
        $tenant = $this->makeTenant();

        $code   = Artisan::call('stripe:check-readiness', ['--tenant' => [$tenant->getTenantKey()]]);
        $output = Artisan::output();

        $this->assertSame(0, $code, $output);
        $this->assertStringContainsString('✓ STRIPE_SECRET: configured (test mode)', $output);
        $this->assertStringContainsString('✓ STRIPE_CONNECT_WEBHOOK_SECRET: configured', $output);
        $this->assertStringContainsString('/stripe/connect/webhook', $output);
        $this->assertStringContainsString('present in 1/1 tenant(s)', $output);
        foreach (['sk_test_offline_fake', 'whsec_test_offline_platform', 'whsec_test_offline_connect', 'ca_test_offline', 'offline'] as $secret) {
            $this->assertStringNotContainsString($secret, $output);
        }
    }

    public function test_readiness_check_fails_closed_on_missing_config_and_http_in_production(): void
    {
        config([
            'services.stripe.connect_webhook_secret' => null,
            'services.stripe.webhook_secret'         => 'not-a-webhook-secret',
            'app.url'                                => 'http://portal.example.test',
        ]);
        $this->app['env'] = 'production';

        $code   = Artisan::call('stripe:check-readiness', ['--skip-tenants' => true]);
        $output = Artisan::output();

        $this->assertSame(1, $code);
        $this->assertStringContainsString('✗ STRIPE_CONNECT_WEBHOOK_SECRET: missing', $output);
        $this->assertStringContainsString('✗ STRIPE_WEBHOOK_SECRET: configured but unexpected format', $output);
        $this->assertStringContainsString('✗ APP_URL: must be https in production', $output);
        $this->assertStringContainsString('test-mode key in production', $output);
        $this->assertStringNotContainsString('not-a-webhook-secret', $output);
    }
}
