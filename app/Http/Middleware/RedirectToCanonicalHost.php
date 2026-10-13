<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * 301s production host aliases (www.fakturalista.com) to the canonical host
 * (config('seo.canonical_url')), keeping path and query, so search engines
 * see one copy of each public page.
 */
class RedirectToCanonicalHost
{
    public function handle(Request $request, Closure $next)
    {
        if (in_array(strtolower($request->getHost()), config('seo.redirect_hosts', []), true)) {
            return redirect()->away(config('seo.canonical_url') . $request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
