<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceSubscription
{
    // Routes that must always work regardless of subscription status.
    private const ALWAYS_ALLOWED = [
        'api/login',
        'api/logout',
        'api/user',
        'api/onboarding',
        'api/subscription',
        'api/subscription/checkout',
        'api/subscription/cancel',
        'api/forgot-password',
        'api/reset-password',
        'api/settings',
        'api/settings/payments/stripe/status',
        'api/settings/payments/stripe/disconnect',
    ];

    // HTTP methods that mutate data — blocked in read-only mode.
    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, Closure $next): Response
    {
        $path = ltrim($request->path(), '/');

        // Whitelisted routes always pass through.
        foreach (self::ALWAYS_ALLOWED as $allowed) {
            if ($path === $allowed || str_starts_with($path, $allowed . '/')) {
                return $next($request);
            }
        }

        $tenant = tenancy()->tenant;

        // Onboarding not done → no subscription state yet, handled by RequireOnboarding.
        if (!$tenant || $tenant->subscription_status === null) {
            return $next($request);
        }

        // Active trial or paid subscription → full access.
        if ($tenant->canAccessApp()) {
            return $next($request);
        }

        // Expired / canceled → read-only: block writes, allow reads.
        if (in_array($request->method(), self::WRITE_METHODS)) {
            return response()->json([
                'error'        => 'subscription_required',
                'message'      => 'Your trial has expired. Subscribe to continue creating and editing.',
                'subscribe_url'=> '/admin/subscription',
            ], 402);
        }

        return $next($request);
    }
}
