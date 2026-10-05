<?php

namespace App\Services;

use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/**
 * Per-tenant usage figures for the backoffice (Filament → Tenants).
 *
 * Every tenant database lives on the central MySQL server (MySQLDatabaseManager,
 * no tenant connection template), so the figures for a whole page of tenants
 * are read in ONE cross-database query on the central connection - no
 * tenancy initialisation and no connection switching per row. A single
 * information_schema lookup first tells which tenant tables/columns exist, so
 * a tenant whose database is missing or not fully migrated degrades to
 * "unavailable" instead of breaking the list.
 *
 * Figures (live, read-only):
 *  - invoices / quotes / customers: current records (soft-deleted excluded,
 *    i.e. what the tenant itself sees)
 *  - users: tenant user accounts
 *  - payments: invoices marked paid - the app records a payment by moving an
 *    invoice to `paid` (+ paid_at / paid_via); there is no separate table
 *  - last_activity: latest create/update across those tables (deleted rows
 *    included, a deletion is activity too)
 *
 * Results are memoised for the request (registered as a scoped singleton).
 */
class TenantUsageStats
{
    private const TABLES = ['invoices', 'quotes', 'customers', 'users'];

    /** @var array<string, array|null> tenant id => stats (null = unavailable) */
    private array $cache = [];

    /**
     * Stats for one tenant. $batch (e.g. the current table page) is loaded in
     * the same query the first time any of its tenants is asked for.
     *
     * @param  iterable<Tenant>  $batch
     */
    public function for(Tenant $tenant, iterable $batch = []): ?array
    {
        if (! array_key_exists($tenant->getTenantKey(), $this->cache)) {
            $pending = [$tenant->getTenantKey() => $tenant];
            foreach ($batch as $other) {
                if ($other instanceof Tenant && ! array_key_exists($other->getTenantKey(), $this->cache)) {
                    $pending[$other->getTenantKey()] = $other;
                }
            }
            $this->load($pending);
        }

        return $this->cache[$tenant->getTenantKey()];
    }

    /** @param array<string, Tenant> $tenants */
    private function load(array $tenants): void
    {
        $central = DB::connection(config('tenancy.database.central_connection'));

        $shared = [];
        foreach ($tenants as $id => $tenant) {
            $this->cache[$id] = null;

            if ($this->usesCentralServer($tenant)) {
                $shared[$id] = $tenant->database()->getName();
            } else {
                // Tenant DB on another server/credentials: read it through its own connection.
                $this->cache[$id] = $tenant->run(fn () => $this->query(
                    DB::connection(), [$id => DB::connection()->getDatabaseName()]
                )[$id] ?? null);
            }
        }

        if ($shared) {
            foreach ($this->query($central, $shared) as $id => $stats) {
                $this->cache[$id] = $stats;
            }
        }
    }

    /**
     * @param  array<string, string>  $schemas  tenant id => database name
     * @return array<string, array>   tenant id => stats, only for databases that exist
     */
    private function query(Connection $connection, array $schemas): array
    {
        $columns = $this->existingColumns($connection, array_values($schemas));

        $selects  = [];
        $bindings = [];
        foreach ($schemas as $id => $schema) {
            if (! isset($columns[$schema])) {
                continue; // database missing - leave the tenant "unavailable"
            }
            $selects[]  = $this->tenantSelect($schema, $columns[$schema]);
            $bindings[] = (string) $id;
        }

        if (! $selects) {
            return [];
        }

        $stats = [];
        foreach ($connection->select(implode("\nUNION ALL\n", $selects), $bindings) as $row) {
            $stats[$row->tenant_id] = [
                'invoices'      => (int) $row->invoices,
                'quotes'        => (int) $row->quotes,
                'customers'     => (int) $row->customers,
                'users'         => (int) $row->users,
                'payments'      => (int) $row->payments,
                'last_activity' => $row->last_activity ? Carbon::parse($row->last_activity) : null,
            ];
        }

        return $stats;
    }

    /** @return array<string, array<string, array<string, true>>> schema => table => column => true */
    private function existingColumns(Connection $connection, array $schemas): array
    {
        $rows = $connection->table('information_schema.COLUMNS')
            ->select(['TABLE_SCHEMA as s', 'TABLE_NAME as t', 'COLUMN_NAME as c'])
            ->whereIn('TABLE_SCHEMA', $schemas)
            ->whereIn('TABLE_NAME', self::TABLES)
            ->whereIn('COLUMN_NAME', ['id', 'status', 'created_at', 'updated_at', 'deleted_at'])
            ->get();

        $columns = [];
        foreach ($rows as $row) {
            $columns[$row->s][$row->t][$row->c] = true;
        }

        return $columns;
    }

    /** One row: tenant_id, the five counts and last_activity, as scalar subqueries. */
    private function tenantSelect(string $schema, array $tables): string
    {
        $count = function (string $table, bool $paidOnly = false) use ($schema, $tables): string {
            if (! isset($tables[$table]) || ($paidOnly && ! isset($tables[$table]['status']))) {
                return '0';
            }
            $where = $paidOnly ? ["`status` = 'paid'"] : [];
            if (isset($tables[$table]['deleted_at'])) {
                $where[] = '`deleted_at` IS NULL';
            }

            return '(SELECT COUNT(*) FROM ' . $this->qualified($schema, $table)
                . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ')';
        };

        // Latest write per table; '1000-01-01' stands in for "no rows" so
        // GREATEST() isn't nulled by an empty table.
        $latest = [];
        foreach (self::TABLES as $table) {
            $column = isset($tables[$table]['updated_at']) ? 'updated_at'
                : (isset($tables[$table]['created_at']) ? 'created_at' : null);
            if ($column) {
                $latest[] = "COALESCE((SELECT MAX(`{$column}`) FROM " . $this->qualified($schema, $table) . "), '1000-01-01')";
            }
        }
        $lastActivity = match (count($latest)) {
            0       => 'NULL',
            1       => "NULLIF({$latest[0]}, '1000-01-01')",
            default => "NULLIF(GREATEST(" . implode(', ', $latest) . "), '1000-01-01')",
        };

        return 'SELECT ? AS tenant_id, '
            . $count('invoices') . ' AS invoices, '
            . $count('quotes') . ' AS quotes, '
            . $count('customers') . ' AS customers, '
            . $count('users') . ' AS users, '
            . $count('invoices', paidOnly: true) . ' AS payments, '
            . $lastActivity . ' AS last_activity';
    }

    private function qualified(string $schema, string $table): string
    {
        return '`' . str_replace('`', '``', $schema) . '`.`' . $table . '`';
    }

    /** True when the tenant DB is reachable with the central connection's server/credentials. */
    private function usesCentralServer(Tenant $tenant): bool
    {
        foreach (['db_connection', 'db_host', 'db_port', 'db_username'] as $key) {
            if ($tenant->getInternal($key) !== null) {
                return false;
            }
        }

        return config('tenancy.database.template_tenant_connection') === null;
    }
}
