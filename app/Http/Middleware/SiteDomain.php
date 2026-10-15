<?php

namespace App\Http\Middleware;

use App\Support\Seo;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Public-site routes are registered once on the host pattern {siteDomain}
 * (routes/web.php). This middleware:
 *  - makes route() generate URLs on the host being visited (fakturalista.test
 *    stays on .test, fakturalista.com on .com) - outside such a request the
 *    default is Seo::siteDomain(), set in AppServiceProvider;
 *  - drops the {siteDomain} parameter so controllers keep receiving only
 *    their own parameters (e.g. BlogController::show($slug)).
 */
class SiteDomain
{
    public const PARAMETER = 'siteDomain';

    public function handle(Request $request, Closure $next)
    {
        $route = $request->route();

        if ($route && $route->hasParameter(self::PARAMETER)) {
            URL::defaults([self::PARAMETER => $route->parameter(self::PARAMETER)]);
            $route->forgetParameter(self::PARAMETER);
        }

        return $next($request);
    }
}
