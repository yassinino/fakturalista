<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $supported = config('app.supported_locales', ['es', 'fr', 'en']);

        // Authenticated user's saved preference takes highest priority
        $locale = $request->user()?->locale;

        // Fall back to session (used by the public site switcher). Guarded
        // by hasSession(): API routes carry no session middleware at all
        // (see app/Http/Kernel.php's 'api' group), so calling session()
        // unconditionally here throws "Session store not set on request"
        // for any authenticated API user whose locale is empty/unsupported.
        if ((! $locale || ! in_array($locale, $supported, true)) && $request->hasSession()) {
            $locale = $request->session()->get('locale');
        }

        // Final fallback to app default (Morocco Phase 1A: 'fr' - see config/app.php)
        if (! $locale || ! in_array($locale, $supported, true)) {
            $locale = config('app.locale', 'fr');
        }

        App::setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
