<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Step 6C - pre-deployment readiness check for Stripe payments (Client
 * Portal / pay links via Stripe Connect, and subscription webhooks).
 *
 * Prints ONLY safe statuses - "configured" / "missing" / "unexpected
 * format", the key's mode (test/live) and the public webhook URL. Never a
 * secret value or any part of one.
 *
 *   php artisan stripe:check-readiness [--skip-tenants]
 *
 * Exit code 1 when anything required is missing.
 */
class StripeReadinessCheck extends Command
{
    protected $signature = 'stripe:check-readiness
                            {--skip-tenants : Do not inspect each tenant database}
                            {--tenant=* : Only inspect these tenant id(s)}';

    protected $description = 'Check Stripe payment configuration, routes and tables (never prints secrets)';

    private int $failures = 0;

    public function handle(): int
    {
        $production = app()->environment('production');

        $this->info('Stripe readiness' . ($production ? ' (production)' : ' (' . app()->environment() . ')'));

        // ── Secrets (presence/format only) ──────────────────────────────
        $this->section('Configuration');
        $secret = (string) config('services.stripe.secret');
        $mode   = str_starts_with($secret, 'sk_live_') || str_starts_with($secret, 'rk_live_') ? 'live'
                : (str_starts_with($secret, 'sk_test_') || str_starts_with($secret, 'rk_test_') ? 'test' : null);
        $this->secretCheck('STRIPE_SECRET', $secret, ['sk_', 'rk_'], $mode ? "{$mode} mode" : null);
        if ($production && $mode === 'test') {
            $this->warnLine('STRIPE_SECRET', 'test-mode key in production');
        }
        $this->secretCheck('STRIPE_WEBHOOK_SECRET', (string) config('services.stripe.webhook_secret'), ['whsec_']);
        $this->secretCheck('STRIPE_CONNECT_WEBHOOK_SECRET', (string) config('services.stripe.connect_webhook_secret'), ['whsec_']);
        $this->secretCheck('STRIPE_CONNECT_CLIENT_ID', (string) config('services.stripe.connect_client_id'), ['ca_']);

        // ── Public URLs (success/cancel URLs + webhook registration) ─────
        $this->section('Application URL');
        $appUrl = (string) config('app.url');
        $scheme = parse_url($appUrl, PHP_URL_SCHEME);
        $host   = parse_url($appUrl, PHP_URL_HOST);

        if (!$host || !in_array($scheme, ['http', 'https'], true)) {
            $this->fail('APP_URL', 'not a valid URL');
        } elseif ($production && $scheme !== 'https') {
            $this->fail('APP_URL', 'must be https in production (Checkout success/cancel URLs and webhooks)');
        } else {
            $this->pass('APP_URL', $scheme === 'https' ? 'valid, https' : 'valid (http - acceptable outside production)');
        }

        // ── Routes ──────────────────────────────────────────────────────
        $this->section('Webhook routes');
        $connect = Route::getRoutes()->getByName('stripe.connect.webhook');
        if ($connect && in_array('POST', $connect->methods(), true) && $connect->uri() === 'stripe/connect/webhook') {
            $this->pass('Connect webhook', 'POST ' . rtrim($appUrl, '/') . '/stripe/connect/webhook  (register as a *Connect* webhook)');
        } else {
            $this->fail('Connect webhook', 'central POST /stripe/connect/webhook route missing');
        }

        $platform = Route::getRoutes()->getByName('stripe.webhook');
        $platform
            ? $this->pass('Platform webhook', 'POST ' . rtrim($appUrl, '/') . '/stripe/webhook  (subscriptions)')
            : $this->fail('Platform webhook', 'POST /stripe/webhook route missing');

        $retired = collect(Route::getRoutes()->getRoutes())->first(fn ($r) => $r->uri() === 'connect/webhook');
        $retired
            ? $this->fail('Retired tenant /connect/webhook', 'still registered')
            : $this->pass('Retired tenant /connect/webhook', 'not registered');

        // ── Tables ──────────────────────────────────────────────────────
        $this->section('Database');
        $central = config('tenancy.database.central_connection', 'mysql');
        Schema::connection($central)->hasTable('stripe_connect_accounts')
            ? $this->pass('stripe_connect_accounts (central)', 'present')
            : $this->fail('stripe_connect_accounts (central)', 'missing - run php artisan migrate');

        if ($this->option('skip-tenants')) {
            $this->line('  - tenant tables: skipped (--skip-tenants)');
        } else {
            $this->checkTenantTables();
        }

        $this->newLine();
        if ($this->failures) {
            $this->error("{$this->failures} check(s) failed.");
            return self::FAILURE;
        }

        $this->info('All required checks passed.');
        return self::SUCCESS;
    }

    private function checkTenantTables(): void
    {
        $total   = 0;
        $missing = [];

        $only = array_filter((array) $this->option('tenant'));

        foreach (Tenant::query()->when($only, fn ($q) => $q->whereIn('id', $only))->orderBy('id')->cursor() as $tenant) {
            $total++;
            try {
                $ok = $tenant->run(fn () => Schema::hasTable('invoice_payment_attempts')
                    && Schema::hasColumn('invoice_payment_attempts', 'purpose'));
            } catch (\Throwable) {
                tenancy()->end();
                $ok = false;
            }

            if (!$ok) {
                $missing[] = $tenant->getTenantKey();
            }
        }

        if (!$missing) {
            $this->pass('invoice_payment_attempts (tenants)', "present in {$total}/{$total} tenant(s)");
            return;
        }

        $shown = implode(', ', array_slice($missing, 0, 10)) . (count($missing) > 10 ? ', …' : '');
        $this->fail('invoice_payment_attempts (tenants)', count($missing) . "/{$total} tenant(s) not migrated ({$shown}) - run php artisan tenants:migrate");
    }

    // ── Output helpers (status text only - never a value) ───────────────

    private function secretCheck(string $name, string $value, array $prefixes, ?string $extra = null): void
    {
        if ($value === '') {
            $this->fail($name, 'missing');
            return;
        }

        foreach ($prefixes as $prefix) {
            if (str_starts_with($value, $prefix)) {
                $this->pass($name, 'configured' . ($extra ? " ({$extra})" : ''));
                return;
            }
        }

        $this->fail($name, 'configured but unexpected format');
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->line("<comment>{$title}</comment>");
    }

    private function pass(string $label, string $status): void
    {
        $this->line("  <info>✓</info> {$label}: {$status}");
    }

    private function fail(string $label, string $status): void
    {
        $this->failures++;
        $this->line("  <error>✗</error> {$label}: {$status}");
    }

    private function warnLine(string $label, string $status): void
    {
        $this->line("  <comment>!</comment> {$label}: {$status}");
    }
}
