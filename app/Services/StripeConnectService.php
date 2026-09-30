<?php

namespace App\Services;

use App\Models\CompanyProfile;
use App\Models\Invoice;
use App\Models\StripeConnectAccount;
use App\Models\Tenant;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Stripe\Account;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Stripe;

class StripeConnectService
{
    // syncCentralMapping() results (Step 6C)
    public const MAP_CREATED   = 'created';
    public const MAP_UNCHANGED = 'unchanged';
    public const MAP_UPDATED   = 'updated';   // stale row of a tenant that no longer holds the account
    public const MAP_REMOVED   = 'removed';   // tenant has no account any more
    public const MAP_CONFLICT  = 'conflict';  // account still held by ANOTHER tenant - left untouched
    public const MAP_SKIPPED   = 'skipped';   // no tenant context / nothing to map

    public const ACCOUNT_IN_USE_MESSAGE = 'This Stripe account is already connected to another Fakturalista workspace. Disconnect it there first, or connect a different Stripe account.';

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

        // Step 6C - one Stripe account belongs to one workspace. Refuse
        // BEFORE touching this tenant's profile, so the existing owner's
        // mapping (and this tenant's current settings) stay exactly as they were.
        $currentTenantId = tenancy()->tenant?->getTenantKey();
        if ($currentTenantId && ($owner = $this->otherOwnerOf($stripeAccountId, $currentTenantId))) {
            Log::warning('Stripe Connect: account already linked to another tenant - link refused', [
                'account_id'   => $stripeAccountId,
                'owner_tenant' => $owner,
                'tenant'       => $currentTenantId,
            ]);
            throw new \RuntimeException(self::ACCOUNT_IN_USE_MESSAGE);
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

        $profile = app(\App\Services\TenantContextService::class)->ensureCompanyProfile();
        $profile->update($data);
        $this->syncCentralMapping($profile);

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
                // Log but don't abort - account may already be deauthorized
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
        $this->syncCentralMapping($profile);
    }

    /**
     * Fetch the latest account status from Stripe and persist it.
     */
    public function refreshAccountStatus(CompanyProfile $profile): void
    {
        // Backfills the central map for tenants linked before it existed -
        // this runs on every Settings > Payments status call.
        $this->syncCentralMapping($profile);

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

    // ── Central account -> tenant map (Step 6A.1) ─────────────────────

    /**
     * Keeps the central stripe_connect_accounts row for the CURRENT tenant
     * in line with its CompanyProfile, so the central Connect webhook can
     * find this tenant from a signed event's `account` alone. Called after
     * every link / refresh / disconnect and by stripe:backfill-connect-accounts;
     * must run inside tenant context. Idempotent.
     *
     * Step 6C: an account still held by ANOTHER tenant is never silently
     * moved - the existing mapping is kept and MAP_CONFLICT returned.
     */
    public function syncCentralMapping(CompanyProfile $profile): string
    {
        $tenantId = tenancy()->tenant?->getTenantKey();

        if (!$tenantId) {
            return self::MAP_SKIPPED;
        }

        $accountId = $profile->stripe_account_id;

        $removed = StripeConnectAccount::where('tenant_id', $tenantId)
            ->when($accountId, fn ($q) => $q->where('stripe_account_id', '!=', $accountId))
            ->delete();

        if (!$accountId) {
            return $removed ? self::MAP_REMOVED : self::MAP_SKIPPED;
        }

        $existing = StripeConnectAccount::where('stripe_account_id', $accountId)->first();

        if (!$existing) {
            StripeConnectAccount::create(['stripe_account_id' => $accountId, 'tenant_id' => $tenantId]);
            return self::MAP_CREATED;
        }

        if ($existing->tenant_id === $tenantId) {
            return self::MAP_UNCHANGED;
        }

        if ($owner = $this->otherOwnerOf($accountId, $tenantId)) {
            Log::warning('Stripe Connect: account is mapped to another tenant that still uses it - mapping NOT changed', [
                'account_id'   => $accountId,
                'owner_tenant' => $owner,
                'tenant'       => $tenantId,
            ]);
            return self::MAP_CONFLICT;
        }

        Log::info('Stripe Connect: stale mapping (previous tenant no longer uses this account) reassigned', [
            'account_id'      => $accountId,
            'previous_tenant' => $existing->tenant_id,
            'tenant'          => $tenantId,
        ]);
        $existing->update(['tenant_id' => $tenantId]);

        return self::MAP_UPDATED;
    }

    /**
     * The id of ANOTHER tenant that the central map says owns this account
     * and whose own profile still holds it - or null when the account is
     * free (unmapped, mapped to $tenantId, or a stale row). A single
     * targeted lookup of the mapped tenant, never a scan. When the owner
     * can't be checked it is assumed to still own the account (fail safe).
     */
    public function otherOwnerOf(string $accountId, string $tenantId): ?string
    {
        $mapping = StripeConnectAccount::where('stripe_account_id', $accountId)->first();

        if (!$mapping || $mapping->tenant_id === $tenantId) {
            return null;
        }

        $owner = Tenant::find($mapping->tenant_id);

        if (!$owner) {
            return null; // tenant gone (the FK normally cascades this row away)
        }

        $stillHeld = $owner->run(function () use ($accountId) {
            try {
                return CompanyProfile::where('stripe_account_id', $accountId)->exists();
            } catch (\Throwable $e) {
                Log::warning('Stripe Connect: could not verify the mapped owner of an account', ['account_id' => $accountId, 'error' => $e->getMessage()]);
                return true;
            }
        });

        return $stillHeld ? $mapping->tenant_id : null;
    }

    /**
     * Applies a Stripe `account.updated` payload to this tenant's profile
     * (moved from the retired tenant-domain StripeConnectController webhook).
     * Must run inside the tenant that owns the account.
     */
    public function applyAccountUpdate(object $account): bool
    {
        $profile = CompanyProfile::where('stripe_account_id', $account->id ?? null)->first();

        if (!$profile) {
            return false;
        }

        $profile->update([
            'stripe_connection_status' => !empty($account->details_submitted) ? 'connected' : 'incomplete',
            'onboarding_completed'     => (bool) ($account->details_submitted ?? false),
            'charges_enabled'          => (bool) ($account->charges_enabled ?? false),
            'payouts_enabled'          => (bool) ($account->payouts_enabled ?? false),
        ]);

        return true;
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

        // Morocco Phase 2A: was hardcoded 'Factura' (Spanish) regardless of
        // tenant - see PaymentController::createSession() for the same fix
        // and rationale.
        $documentWord = match ($profile->locale) {
            'fr'    => 'Facture',
            'es'    => 'Factura',
            default => 'Invoice',
        };

        return StripeSession::create(
            [
                'payment_method_types' => ['card'],
                'line_items'           => [[
                    'price_data' => [
                        'currency'     => $currency,
                        'unit_amount'  => (int) round(($invoice->total ?? 0) * 100),
                        'product_data' => [
                            'name'        => $documentWord . ' ' . $invoice->reference,
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
            // Route this API call to the connected account - funds go directly there
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
