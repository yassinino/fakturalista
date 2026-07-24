<?php

namespace App\Providers;

use App\Models\TenantDomainVisit;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->registerTenantNotFoundHandler();
    }

    private function registerTenantNotFoundHandler(): void
    {
        // Stancl's IdentificationMiddleware catches TenantCouldNotBeIdentifiedException
        // and calls this closure instead of re-throwing, giving us a clean response.
        // This fires BEFORE the Exception Handler - no error page ever reaches the user.
        InitializeTenancyByDomain::$onFail = function ($exception, $request, $next) {
            $domain    = $request->getHost();
            $parts     = explode('.', $domain);
            // 'walid.fakturalista.com' → subdomain='walid', parent='fakturalista.com'
            $subdomain  = count($parts) > 2 ? $parts[0] : null;
            $parentHost = count($parts) > 2 ? implode('.', array_slice($parts, 1)) : $domain;

            // ── 1. Structured log ───────────────────────────────────────────
            Log::warning('Tenant not found for domain', [
                'domain'     => $domain,
                'subdomain'  => $subdomain,
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp'  => now()->toIso8601String(),
            ]);

            // ── 2. Persist to central DB ────────────────────────────────────
            // Tenancy is NOT initialized here (that's why we're in $onFail),
            // so DB::connection() is still the central connection.
            // TenantDomainVisit also hard-codes $connection = 'mysql' as a guard.
            try {
                TenantDomainVisit::create([
                    'domain'     => $domain,
                    'subdomain'  => $subdomain,
                    'ip_address' => $request->ip(),
                    'user_agent' => mb_substr($request->userAgent() ?? '', 0, 1000),
                ]);
            } catch (\Throwable $e) {
                Log::error('Failed to record tenant domain visit', [
                    'domain' => $domain,
                    'error'  => $e->getMessage(),
                ]);
            }

            // ── 3. API clients get a JSON 404, browsers get a redirect ─────
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => "Tenant could not be identified on domain {$domain}",
                ], 404);
            }

            // ── 4. Redirect to the central domain so the URL bar shows a
            //    clean address (e.g. fakturalista.com/tenant-not-found).
            //    The parent host is computed from the subdomain structure so
            //    this works in every environment automatically:
            //      walid.fakturalista.com  → fakturalista.com/tenant-not-found
            //      walid.fakturalista.test → fakturalista.test/tenant-not-found
            $scheme      = $request->isSecure() ? 'https' : 'http';
            $redirectUrl = $scheme . '://' . $parentHost
                . '/tenant-not-found?domain=' . urlencode($domain);

            return redirect()->away($redirectUrl);
        };
    }
}
