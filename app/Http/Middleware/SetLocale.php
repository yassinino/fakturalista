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
        $locale = $request->session()->get('locale');

        if (! $locale || ! in_array($locale, $supported, true)) {
            $locale = config('app.locale', 'es');
        }

        App::setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
