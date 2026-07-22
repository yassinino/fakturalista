<?php

namespace App\Services;

use App\Models\CompanyProfile;
use App\Models\Invoice;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Stripe\Account;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Stripe;

class StripeConnectService
{
    private const OAUTH_AUTHORIZE_URL = 'https://connect.stripe.com/oauth/authorize';
    private const OAUTH_TOKEN_URL     = 'https://connect.stripe.com/oauth/token';
    private const OAUTH_DEAUTH_URL    = 'https://connect.stripe.com/oauth/deauthorize';

    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    // ── OAuth ─────────────────────────────────────────────────────────

    public function generateOAuthUrl(string $redirectUri, string $state): string
    {
        return self::OAUTH_AUTHORIZE_URL . '?' . http_build_query([
            'response_type' => 'code',
            'client_id'     => config('services.stripe.connect_client_id'),
            'scope'         => 'read_write',
            'redirect_uri'  => $redirectUri,
            'state'         => $state,
        ]);
    }

    /**
     * Exchange the OAuth authorization code for a connected account ID and
     * save the account details to the CompanyProfile.
     *
     * @throws \RuntimeException on Stripe or HTTP error
     */
    public function handleCallback(string $code): array
    {
        $response = Http::asForm()
            ->withToken(config('services.stripe.secret'))
            ->post(self::OAUTH_TOKEN_URL, [
                'grant_type' => 'authorization_code',
                'code'       => $code,
            ]);

        if ($response->failed()) {
            $desc = $response->json('error_description', 'OAuth token exchange failed');
            Log::error('Stripe Connect OAuth token exchange failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \RuntimeException($desc);
        }

        $oauthData       = $response->json();
        $stripeAccountId = $oauthData['stripe_user_id'] ?? null;

        if (!$stripeAccountId) {
            throw new \RuntimeException('No stripe_user_id in OAuth response.');
        }

        // Retrieve full account info
        $account = Account::retrieve($stripeAccountId);

        $data = [
            'stripe_account_id'        => $stripeAccountId,
            'stripe_connection_status' => $account->details_submitted ? 'connected' : 'incomplete',
            'onboarding_completed'     => (bool) $account->details_submitted,
            'charges_enabled'          => (bool) $account->charges_enabled,
            'payouts_enabled'          => (bool) $account->payouts_enabled,
            'stripe_connected_at'      => now(),
        ];

        $profile = CompanyProfile::firstOrCreate([], ['legal_name' => '']);
        $profile->update($data);

        Log::info('Stripe Connect: account linked', [
            'account_id'           => $stripeAccountId,
            'onboarding_completed' => $data['onboarding_completed'],
            'charges_enabled'      => $data['charges_enabled'],
        ]);

        return $data;
    }

    /**
     * Deauthorize and clear all Stripe Connect data from the profile.
     */
    public function disconnect(CompanyProfile $profile): void
    {
        if ($profile->stripe_account_id) {
            $response = Http::asForm()
                ->withToken(config('services.stripe.secret'))
                ->post(self::OAUTH_DEAUTH_URL, [
                    'client_id'      => config('services.stripe.connect_client_id'),
                    'stripe_user_id' => $profile->stripe_account_id,
                ]);

            if ($response->failed()) {
                // Log but don't abort — account may already be deauthorized
                Log::warning('Stripe Connect deauthorize failed (may already be disconnected)', [
                    'account_id' => $profile->stripe_account_id,
                    'status'     => $response->status(),
                ]);
            } else {
                Log::info('Stripe Connect: account deauthorized', [
                    'account_id' => $profile->stripe_account_id,
                ]);
            }
        }

        $profile->update([
            'stripe_account_id'        => null,
            'stripe_connection_status' => null,
            'onboarding_completed'     => false,
            'charges_enabled'          => false,
            'payouts_enabled'          => false,
            'stripe_connected_at'      => null,
        ]);
    }

    /**
     * Fetch the latest account status from Stripe and persist it.
     */
    public function refreshAccountStatus(CompanyProfile $profile): void
    {
        if (!$profile->stripe_account_id) {
            return;
        }

        try {
            $account = Account::retrieve($profile->stripe_account_id);

            $profile->update([
                'stripe_connection_status' => $account->details_submitted ? 'connected' : 'incomplete',
                'onboarding_completed'     => (bool) $account->details_submitted,
                'charges_enabled'          => (bool) $account->charges_enabled,
                'payouts_enabled'          => (bool) $account->payouts_enabled,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Stripe Connect: failed to refresh account status', [
                'account_id' => $profile->stripe_account_id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    // ── Validation ────────────────────────────────────────────────────

    /**
     * Returns true only if the account is fully connected and can charge.
     */
    public function canAcceptPayments(CompanyProfile $profile): bool
    {
        return !empty($profile->stripe_account_id)
            && $profile->onboarding_completed
            && $profile->charges_enabled;
    }

    // ── Checkout session ──────────────────────────────────────────────

    /**
     * Create a Stripe Checkout Session routed to the connected account.
     * The payment goes DIRECTLY to the merchant's Stripe account.
     *
     * @throws \Stripe\Exception\ApiErrorException
     */
    public function createConnectedCheckoutSession(
        Invoice        $invoice,
        CompanyProfile $profile,
        string         $successUrl,
        string         $cancelUrl
    ): StripeSession {
        $companyName = $profile->trade_name ?: $profile->legal_name ?: config('app.name');
        $currency    = strtolower($profile->currency ?: 'eur');

        return StripeSession::create(
            [
                'payment_method_types' => ['card'],
                'line_items'           => [[
                    'price_data' => [
                        'currency'     => $currency,
                        'unit_amount'  => (int) round(($invoice->total ?? 0) * 100),
                        'product_data' => [
                            'name'        => 'Factura ' . $invoice->reference,
                            'description' => $companyName,
                        ],
                    ],
                    'quantity' => 1,
                ]],
                'mode'        => 'payment',
                'metadata'    => [
                    'invoice_uuid' => $invoice->uuid,
                    'tenant_id'    => tenancy()->tenant?->id ?? '',
                ],
                'success_url' => $successUrl,
                'cancel_url'  => $cancelUrl,
                'expires_at'  => now()->addHours(23)->timestamp,
            ],
            // Route this API call to the connected account — funds go directly there
            ['stripe_account' => $profile->stripe_account_id]
        );
    }

    // ── Account status helpers ────────────────────────────────────────

    public function formatStatus(CompanyProfile $profile): array
    {
        return [
            'connected'            => !empty($profile->stripe_account_id),
            'account_id'           => $profile->stripe_account_id,
            'connection_status'    => $profile->stripe_connection_status,
            'onboarding_completed' => (bool) $profile->onboarding_completed,
            'charges_enabled'      => (bool) $profile->charges_enabled,
            'payouts_enabled'      => (bool) $profile->payouts_enabled,
            'connected_at'         => $profile->stripe_connected_at?->toISOString(),
        ];
    }
}
