<?php

namespace App\Http\Controllers;

use App\Services\StripeConnectService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class StripeConnectController extends Controller
{
    public function __construct(private readonly StripeConnectService $stripe) {}

    // ── Web routes (redirect + callback) ──────────────────────────────

    /**
     * Redirect the user to Stripe's OAuth page to connect their account.
     * Route: GET /settings/payments/stripe/connect  (name: stripe.connect)
     */
    public function redirect(Request $request)
    {
        $state = Str::random(40);
        $request->session()->put('stripe_connect_state', $state);

        // Use url() instead of route() - in domain-based multi-tenant routing, url() correctly
        // uses the current request host (e.g., tenant.fakturalista.com), while route() can fail
        // to resolve named routes when the tenant context hasn't fully bootstrapped.
        $callbackUrl = url('/settings/payments/stripe/callback');
        $oauthUrl    = $this->stripe->generateOAuthUrl($callbackUrl, $state);

        Log::info('Stripe Connect: redirecting to OAuth', [
            'callback_url' => $callbackUrl,
            'oauth_url'    => $oauthUrl,
            'tenant'       => tenancy()->tenant?->id,
        ]);

        return redirect($oauthUrl);
    }

    /**
     * Handle Stripe's OAuth redirect after the user authorizes the connection.
     * Route: GET /settings/payments/stripe/callback  (name: stripe.connect.callback)
     */
    public function callback(Request $request)
    {
        Log::info('Stripe Connect: callback received', [
            'has_code'  => $request->has('code'),
            'has_error' => $request->has('error'),
            'tenant'    => tenancy()->tenant?->id,
        ]);

        // Handle user-cancelled OAuth
        if ($request->has('error')) {
            $desc = $request->get('error_description', 'Stripe connection was cancelled.');
            Log::info('Stripe Connect: OAuth cancelled by user', ['error' => $desc]);
            return redirect('/admin/settings?stripe_error=' . urlencode($desc));
        }

        $code  = $request->get('code');
        $state = $request->get('state');

        // CSRF state check
        $expectedState = $request->session()->pull('stripe_connect_state');
        if (!$expectedState || $state !== $expectedState) {
            Log::warning('Stripe Connect: invalid or missing state parameter', [
                'received' => $state,
                'expected' => $expectedState ? '[set]' : '[missing]',
            ]);
            return redirect('/admin/settings?stripe_error=' . urlencode('Invalid state. Please try again.'));
        }

        try {
            $result = $this->stripe->handleCallback($code);
            Log::info('Stripe Connect: account connected successfully', [
                'account_id'           => $result['stripe_account_id'] ?? null,
                'onboarding_completed' => $result['onboarding_completed'] ?? null,
                'charges_enabled'      => $result['charges_enabled'] ?? null,
            ]);
            return redirect('/admin/settings?stripe_connected=1');
        } catch (\Throwable $e) {
            Log::error('Stripe Connect: callback failed', [
                'message' => $e->getMessage(),
                'tenant'  => tenancy()->tenant?->id,
            ]);
            return redirect('/admin/settings?stripe_error=' . urlencode($e->getMessage()));
        }
    }

    // ── API routes (JSON) ─────────────────────────────────────────────

    /**
     * Return the current Stripe Connect status.
     * Route: GET /api/settings/payments/stripe/status
     */
    public function status(Request $request)
    {
        $profile = app(\App\Services\TenantContextService::class)->ensureCompanyProfile();

        // Refresh from Stripe on every status call so the UI always reflects reality
        $this->stripe->refreshAccountStatus($profile);

        return response()->json($this->stripe->formatStatus($profile));
    }

    /**
     * Disconnect the Stripe account.
     * Routes:
     *   POST /api/settings/payments/stripe/disconnect  (API, auth-guarded, returns JSON)
     *   POST /settings/payments/stripe/disconnect      (web, name: stripe.disconnect)
     */
    public function disconnect(Request $request)
    {
        $profile = app(\App\Services\TenantContextService::class)->ensureCompanyProfile();

        if (empty($profile->stripe_account_id)) {
            $msg = 'No Stripe account is connected.';
            return $request->expectsJson()
                ? response()->json(['message' => $msg], 422)
                : redirect('/admin/settings?stripe_error=' . urlencode($msg));
        }

        $accountId = $profile->stripe_account_id;
        $this->stripe->disconnect($profile);

        Log::info('Stripe Connect: account disconnected', [
            'account_id' => $accountId,
            'tenant'     => tenancy()->tenant?->id,
        ]);

        return $request->expectsJson()
            ? response()->json(['message' => 'Stripe account disconnected successfully.'])
            : redirect('/admin/settings?stripe_disconnected=1');
    }

    // The Connect webhook (handleWebhook + its checkout/payment/refund/
    // account.updated handlers) moved to the central
    // App\Http\Controllers\StripeConnectWebhookController in Step 6A.1 -
    // account.updated now goes through StripeConnectService::applyAccountUpdate().
}
