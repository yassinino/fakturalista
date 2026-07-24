<?php

declare(strict_types=1);

namespace App\Jobs;

use Database\Seeders\CountrySeeder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Contracts\TenantWithDatabase;

class SeedTenantCountries implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected TenantWithDatabase $tenant;

    public function __construct(TenantWithDatabase $tenant)
    {
        $this->tenant = $tenant;
    }

    public function handle(): void
    {
        // tenancy()->runForMultiple() switches to the tenant DB, runs the callback,
        // then reverts to the central context - exactly what MigrateDatabase does
        // via `tenants:migrate`. We do the same by seeding inside the tenant context.
        try {
            tenancy()->runForMultiple(
                [$this->tenant->getTenantKey()],
                function () {
                    (new CountrySeeder())->run();
                }
            );

            Log::info('CountrySeeder completed for tenant', [
                'tenant_id' => $this->tenant->getTenantKey(),
            ]);
        } catch (\Throwable $e) {
            // Log but do not re-throw: a seeding failure must never abort provisioning.
            Log::error('CountrySeeder failed for tenant', [
                'tenant_id' => $this->tenant->getTenantKey(),
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);
        }
    }
}
