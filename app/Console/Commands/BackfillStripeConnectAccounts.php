<?php

namespace App\Console\Commands;

use App\Models\CompanyProfile;
use App\Models\StripeConnectAccount;
use App\Models\Tenant;
use App\Services\StripeConnectService;
use Illuminate\Console\Command;

/**
 * Step 6C - one-off, idempotent maintenance: fills the central
 * stripe_connect_accounts map (account -> tenant) for tenants that linked
 * Stripe Connect before that map existed (Step 6A.1). Without a row, the
 * central Connect webhook ignores that tenant's account.updated events.
 *
 * Walks tenants explicitly (this is the ONLY place that does so - webhook
 * requests never scan tenants), reads each tenant's own CompanyProfile
 * inside tenancy, and applies StripeConnectService::syncCentralMapping() -
 * the same rule linking uses, including: an account still held by another
 * tenant is reported as a conflict and never reassigned.
 *
 *   php artisan stripe:backfill-connect-accounts [--dry-run] [--tenant=ID ...]
 */
class BackfillStripeConnectAccounts extends Command
{
    protected $signature = 'stripe:backfill-connect-accounts
                            {--dry-run : Report what would change without writing anything}
                            {--tenant=* : Only these tenant id(s) (default: every tenant)}';

    protected $description = 'Populate the central Stripe Connect account -> tenant map from each tenant\'s CompanyProfile (idempotent)';

    public function handle(StripeConnectService $connect): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $counts = array_fill_keys([
            StripeConnectService::MAP_CREATED, StripeConnectService::MAP_UPDATED, StripeConnectService::MAP_UNCHANGED,
            StripeConnectService::MAP_REMOVED, StripeConnectService::MAP_CONFLICT, StripeConnectService::MAP_SKIPPED, 'error',
        ], 0);
        $conflicts = [];

        $only = array_filter((array) $this->option('tenant'));

        foreach (Tenant::query()->when($only, fn ($q) => $q->whereIn('id', $only))->orderBy('id')->cursor() as $tenant) {
            $tenantId = $tenant->getTenantKey();

            try {
                $result = $tenant->run(function () use ($connect, $dryRun, $tenantId) {
                    $profile = CompanyProfile::first();

                    if (!$profile) {
                        return StripeConnectService::MAP_SKIPPED;
                    }

                    return $dryRun ? $this->predict($connect, $profile, $tenantId) : $connect->syncCentralMapping($profile);
                });
            } catch (\Throwable $e) {
                // tenant.run() doesn't restore on exceptions - make sure the
                // next tenant starts from central context.
                tenancy()->end();
                $counts['error']++;
                $this->warn("  ! {$tenantId}: could not be read ({$e->getMessage()})");
                continue;
            }

            $counts[$result]++;

            if ($result === StripeConnectService::MAP_CONFLICT) {
                $conflicts[] = $tenantId;
                $this->error("  ✗ {$tenantId}: its Stripe account is already mapped to another tenant that still uses it - NOT changed");
            } elseif (in_array($result, [StripeConnectService::MAP_CREATED, StripeConnectService::MAP_UPDATED, StripeConnectService::MAP_REMOVED], true)) {
                $this->line("  ✓ {$tenantId}: {$result}" . ($dryRun ? ' (dry run)' : ''));
            }
        }

        $this->newLine();
        $this->info(($dryRun ? '[dry run] ' : '') . 'Stripe Connect account map:');
        foreach ($counts as $status => $count) {
            $this->line(sprintf('  %-10s %d', $status, $count));
        }

        if ($conflicts) {
            $this->newLine();
            $this->error(count($conflicts) . ' conflict(s): the same Stripe account is linked in more than one workspace. Resolve manually (disconnect it from the wrong workspace), then run this command again.');
        }

        return $conflicts || $counts['error'] ? self::FAILURE : self::SUCCESS;
    }

    /** What syncCentralMapping() would return, without writing. */
    private function predict(StripeConnectService $connect, CompanyProfile $profile, string $tenantId): string
    {
        $accountId = $profile->stripe_account_id;
        $ownRows   = StripeConnectAccount::where('tenant_id', $tenantId)->pluck('stripe_account_id');

        if (!$accountId) {
            return $ownRows->isNotEmpty() ? StripeConnectService::MAP_REMOVED : StripeConnectService::MAP_SKIPPED;
        }

        $existing = StripeConnectAccount::where('stripe_account_id', $accountId)->first();

        return match (true) {
            !$existing                                      => StripeConnectService::MAP_CREATED,
            $existing->tenant_id === $tenantId              => StripeConnectService::MAP_UNCHANGED,
            (bool) $connect->otherOwnerOf($accountId, $tenantId) => StripeConnectService::MAP_CONFLICT,
            default                                         => StripeConnectService::MAP_UPDATED,
        };
    }
}
