<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Self-service trial signup (routes/web.php POST /register). This
        // endpoint provisions a full tenant database per successful
        // submission - far more expensive than an ordinary request, and a
        // realistic abuse target (see the task's "RATE LIMITING / ABUSE
        // PROTECTION" requirement). Deliberately tighter than the generic
        // 'api' limiter above and keyed by IP only (no authenticated user
        // exists yet at this point). Limited by both IP and the attempted
        // email, so one IP genuinely trying several different real
        // signups isn't blocked by someone else hammering a single address.
        RateLimiter::for('register', function (Request $request) {
            return [
                Limit::perMinutes(10, 5)->by('register-ip:' . $request->ip()),
                Limit::perMinutes(10, 3)->by('register-email:' . strtolower((string) $request->input('email'))),
            ];
        });

        // "Find my workspace" lookup (routes/web.php POST /login) - read-only,
        // but still capable of confirming/denying whether an email has an
        // account, so it gets its own light limiter rather than none.
        RateLimiter::for('login-finder', function (Request $request) {
            return Limit::perMinutes(10, 10)->by($request->ip());
        });
    }
}
