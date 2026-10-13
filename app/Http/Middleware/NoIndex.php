<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Keeps private surfaces (tenant workspaces, client portal, payment pages,
 * backoffice) out of search engines, whatever the page itself renders.
 */
class NoIndex
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
