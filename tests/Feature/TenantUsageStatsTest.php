<?php

namespace Tests\Feature;

use App\Filament\Resources\TenantResource\Pages\ListTenants;
use App\Filament\Resources\TenantResource\Pages\ViewTenant;
use App\Models\AdminUser;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantUsageStats;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Backoffice (Filament → Tenants) usage statistics, read from each tenant's
 * own database by App\Services\TenantUsageStats.
 */
class TenantUsageStatsTest extends TestCase
{
    /** @var Tenant[] */
    private array $tenants = [];

    protected function tearDown(): void
    {
        foreach ($this->tenants as $tenant) {
            try {
                $tenant->delete(); // drops the tenant DB as well
            } catch (\Throwable) {
                DB::connection('mysql')->table('domains')->where('tenant_id', $tenant->id)->delete();
                DB::connection('mysql')->table('tenants')->where('id', $tenant->id)->delete();
            }
        }
        AdminUser::where('email', 'like', 'usage-admin-%')->delete();

        parent::tearDown();
    }

    private function makeTenant(string $company): Tenant
    {
        $id     = 'test-usage-' . uniqid();
        $tenant = Tenant::create(['id' => $id, 'company_name' => $company]);
        $tenant->domains()->create(['domain' => $id . '.fakturalista.test']);

        return $this->tenants[] = $tenant;
    }

    /** Seeds: users, customers (one deleted), invoices (one paid, one deleted), quotes. */
    private function seedUsage(Tenant $tenant, int $users, int $customers, int $invoices, int $paid, int $quotes): void
    {
        $tenant->run(function () use ($users, $customers, $invoices, $paid, $quotes) {
            User::factory()->count($users)->create();
            $made = Customer::factory()->count($customers + 1)->create(['type' => 1]);
            $made->last()->delete(); // soft-deleted: not counted
            $customer = $made->first();

            $invoice = fn (string $status) => Invoice::create([
                'uuid' => Str::uuid()->toString(), 'reference' => 'INV-USG-' . uniqid(),
                'customer_id' => $customer->id, 'date' => now()->toDateString(),
                'expiration_date' => now()->addDays(30)->toDateString(), 'status' => $status,
                'sub_total' => 100, 'vta' => 0, 'vta4' => 0, 'vta10' => 0, 'vta21' => 0, 'total' => 100,
            ]);
            for ($i = 0; $i < $invoices - $paid; $i++) {
                $invoice(Invoice::STATUS_DRAFT);
            }
            for ($i = 0; $i < $paid; $i++) {
                $invoice(Invoice::STATUS_PAID);
            }
            $invoice(Invoice::STATUS_PAID)->delete(); // soft-deleted paid invoice: not counted

            for ($i = 0; $i < $quotes; $i++) {
                Quote::create([
                    'uuid' => Str::uuid()->toString(), 'reference' => 'QUO-USG-' . uniqid(),
                    'customer_id' => $customer->id, 'date' => now()->toDateString(),
                    'expiration_date' => now()->addDays(30)->toDateString(), 'status' => Quote::STATUS_SENT,
                    'sub_total' => 50, 'vta' => 0, 'total' => 50,
                ]);
            }
        });
    }

    /** @test */
    public function it_reads_each_tenants_figures_from_its_own_database(): void
    {
        $a = $this->makeTenant('Usage A');
        $b = $this->makeTenant('Usage B');
        $this->seedUsage($a, users: 2, customers: 3, invoices: 4, paid: 1, quotes: 2);
        $this->seedUsage($b, users: 1, customers: 1, invoices: 1, paid: 1, quotes: 0);

        // Pin last activity: everything old, except one quote in A.
        $a->run(function () {
            foreach (['users', 'customers', 'invoices', 'quotes'] as $table) {
                DB::table($table)->update(['updated_at' => '2026-01-01 08:00:00']);
            }
            DB::table('quotes')->orderBy('id')->limit(1)->update(['updated_at' => '2026-03-04 10:15:00']);
        });

        $stats = app(TenantUsageStats::class);
        $sa    = $stats->for($a, [$a, $b]);
        $sb    = $stats->for($b);

        $this->assertSame(
            ['invoices' => 4, 'quotes' => 2, 'customers' => 3, 'users' => 2, 'payments' => 1],
            array_diff_key($sa, ['last_activity' => true])
        );
        $this->assertSame('2026-03-04 10:15:00', $sa['last_activity']->format('Y-m-d H:i:s'));

        $this->assertSame(
            ['invoices' => 1, 'quotes' => 0, 'customers' => 1, 'users' => 1, 'payments' => 1],
            array_diff_key($sb, ['last_activity' => true])
        );
        $this->assertNotNull($sb['last_activity']);
    }

    /** @test */
    public function a_whole_page_is_loaded_in_two_central_queries_without_initialising_tenancy(): void
    {
        $tenants = [$this->makeTenant('Usage Q1'), $this->makeTenant('Usage Q2'), $this->makeTenant('Usage Q3')];
        $this->seedUsage($tenants[0], users: 1, customers: 1, invoices: 1, paid: 0, quotes: 1);

        $queries = [];
        DB::listen(function ($q) use (&$queries) {
            $queries[] = $q->connectionName;
        });

        $stats = app(TenantUsageStats::class);
        foreach ($tenants as $tenant) {
            $this->assertIsArray($stats->for($tenant, $tenants)); // every row of the "page"
        }

        $this->assertSame(['mysql', 'mysql'], $queries, 'information_schema lookup + one UNION query, regardless of tenant count');
        $this->assertFalse(tenancy()->initialized);
        $this->assertSame(0, $stats->for($tenants[2])['invoices']);
    }

    /** @test */
    public function a_tenant_whose_database_is_missing_is_reported_unavailable_without_breaking_the_others(): void
    {
        $ok   = $this->makeTenant('Usage OK');
        $gone = $this->makeTenant('Usage Gone');
        $this->seedUsage($ok, users: 1, customers: 2, invoices: 0, paid: 0, quotes: 0);
        DB::connection('mysql')->statement('DROP DATABASE `' . $gone->database()->getName() . '`');

        $stats = app(TenantUsageStats::class);

        $this->assertNull($stats->for($gone, [$ok, $gone]));
        $this->assertSame(2, $stats->for($ok)['customers']);
    }

    /** @test */
    public function the_backoffice_list_and_view_pages_show_the_usage_figures(): void
    {
        $tenant = $this->makeTenant('Usage Filament ' . uniqid());
        $this->seedUsage($tenant, users: 3, customers: 5, invoices: 7, paid: 2, quotes: 4);

        $this->actingAs(AdminUser::create([
            'name' => 'Usage admin', 'email' => 'usage-admin-' . uniqid() . '@example.test', 'password' => bcrypt(Str::random(32)),
        ]), 'admin');

        Livewire::test(ListTenants::class)
            ->searchTable($tenant->company_name)
            ->assertCanSeeTableRecords([$tenant])
            ->assertTableColumnStateSet('usage_invoices', 7, $tenant)
            ->assertTableColumnStateSet('usage_quotes', 4, $tenant)
            ->assertTableColumnStateSet('usage_customers', 5, $tenant)
            ->assertTableColumnStateSet('usage_users', 3, $tenant)
            ->assertTableColumnStateSet('usage_payments', 2, $tenant)
            ->assertSee(['Facturas', 'Presupuestos', 'Clientes', 'Usuarios', 'Última actividad', 'Plan']);

        Livewire::test(ViewTenant::class, ['record' => $tenant->getRouteKey()])
            ->assertOk()
            ->assertSee(['Uso', 'Facturas', 'Pagos registrados', 'Última actividad']);
    }
}
