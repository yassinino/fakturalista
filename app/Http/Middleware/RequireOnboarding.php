<?php

namespace App\Http\Middleware;

use App\Models\CompanyProfile;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireOnboarding
{
    // Routes excluded from the onboarding check (always accessible once authenticated).
    private const WHITELIST = [
        'api/onboarding',
        'api/logout',
        'api/user',
        'api/subscription',
        'api/subscription/checkout',
        'api/subscription/cancel',
        'api/settings/payments/stripe/status',
        'api/settings/payments/stripe/disconnect',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $path = ltrim($request->path(), '/');

        foreach (self::WHITELIST as $allowed) {
            if ($path === $allowed || str_starts_with($path, $allowed . '/')) {
                return $next($request);
            }
        }

        $profile = CompanyProfile::first();

        if (!$profile || $profile->onboarding_completed_at === null) {
            return response()->json([
                'error'   => 'onboarding_required',
                'message' => 'Please complete your account setup before using the app.',
            ], 403);
        }

        return $next($request);
    }
}
