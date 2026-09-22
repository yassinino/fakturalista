<?php

namespace App\Http\Middleware;

use App\Services\TenantContextService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Morocco Phase 1A VERI*FACTU isolation (docs/morocco-phase-1a-implementation.md).
 * Backend guard so hiding the Settings UI is never the only protection - a
 * Moroccan tenant hitting a VERI*FACTU route directly must still be refused.
 * Nothing in app/Services/Verifactu/* is touched; this only decides who may
 * reach the three certificate routes.
 */
class RequireSpainCountry
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!app(TenantContextService::class)->isSpain()) {
            return response()->json([
                'error'   => 'not_available_for_country',
                'message' => 'VERI*FACTU is only available for Spanish tenants.',
            ], 403);
        }

        return $next($request);
    }
}
