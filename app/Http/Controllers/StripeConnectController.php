<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use App\Models\Invoice;
use App\Models\InvoiceHistory;
use App\Services\StripeConnectService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;

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

        // Use url() instead of route() — in domain-based multi-tenant routing, url() correctly
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
        $profile = CompanyProfile::firstOrCreate([], ['legal_name' => '']);

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
        $profile = CompanyProfile::firstOrCreate([], ['legal_name' => '']);

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

    // ── Webhook ───────────────────────────────────────────────────────

    /**
     * Handle Stripe Connect webhook events.
     * Route: POST /connect/webhook  (no CSRF)
     *
     * Register this URL in your Stripe Dashboard → Webhooks
     * as a "Connect webhook" to receive events from connected accounts.
     */
    public function handleWebhook(Request $request)
    {
        $payload   = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret    = config('services.stripe.connect_webhook_secret');

        try {
            $event = $secret
                ? Webhook::constructEvent($payload, $sigHeader, $secret)
                : json_decode($payload);
        } catch (\Throwable $e) {
            Log::error('Stripe Connect webhook signature verification failed', [
                'error' => $e->getMessage(),
            ]);
            return response('Invalid signature', 400);
        }

        $type = $event->type ?? null;

        Log::info('Stripe Connect webhook received', [
            'type'    => $type,
            'account' => $event->account ?? null,
        ]);

        switch ($type) {
            case 'checkout.session.completed':
                $this->onCheckoutCompleted($event->data->object);
                break;

            case 'payment_intent.payment_failed':
                $this->onPaymentFailed($event->data->object);
                break;

            case 'charge.refunded':
                $this->onChargeRefunded($event->data->object);
                break;

            case 'account.updated':
                $this->onAccountUpdated($event->data->object);
                break;

            default:
                Log::info('Stripe Connect webhook: event ignored', ['type' => $type]);
        }

        return response('OK', 200);
    }

    // ── Webhook event handlers ────────────────────────────────────────

    protected function onCheckoutCompleted(object $session): void
    {
        $invoiceUuid = $session->metadata->invoice_uuid ?? null;

        if (!$invoiceUuid) {
            Log::info('Stripe Connect checkout.session.completed: no invoice_uuid in metadata');
            return;
        }

        $invoice = Invoice::where('uuid', $invoiceUuid)->first();

        if (!$invoice) {
            Log::warning('Stripe Connect checkout.session.completed: invoice not found', [
                'uuid' => $invoiceUuid,
            ]);
            return;
        }

        if ($invoice->isPaid()) {
            return; // idempotent
        }

        $invoice->update([
            'status'  => Invoice::STATUS_PAID,
            'paid_at' => now(),
            'paid_via'=> 'stripe',
        ]);

        $invoice->logHistory(InvoiceHistory::ACTION_PAID, [
            'via'        => 'stripe_connect',
            'session_id' => $session->id,
        ]);

        Log::info('Stripe Connect: invoice marked paid', [
            'invoice_uuid' => $invoiceUuid,
            'session_id'   => $session->id,
        ]);
    }

    protected function onPaymentFailed(object $intent): void
    {
        Log::warning('Stripe Connect: payment_intent.payment_failed', [
            'intent_id'       => $intent->id ?? null,
            'last_error'      => $intent->last_payment_error->message ?? null,
        ]);
        // Extend here to notify the merchant or update invoice status if needed
    }

    protected function onChargeRefunded(object $charge): void
    {
        $invoiceUuid = $charge->metadata->invoice_uuid ?? null;

        Log::info('Stripe Connect: charge.refunded', [
            'charge_id'    => $charge->id ?? null,
            'invoice_uuid' => $invoiceUuid,
            'amount'       => $charge->amount_refunded ?? null,
        ]);

        // Extend here to mark invoice as refunded / create credit note if needed
    }

    protected function onAccountUpdated(object $account): void
    {
        $profile = CompanyProfile::where('stripe_account_id', $account->id)->first();

        if (!$profile) {
            Log::info('Stripe Connect: account.updated — no matching profile', [
                'account_id' => $account->id,
            ]);
            return;
        }

        $profile->update([
            'stripe_connection_status' => $account->details_submitted ? 'connected' : 'incomplete',
            'onboarding_completed'     => (bool) $account->details_submitted,
            'charges_enabled'          => (bool) $account->charges_enabled,
            'payouts_enabled'          => (bool) $account->payouts_enabled,
        ]);

        Log::info('Stripe Connect: account.updated — profile synced', [
            'account_id'           => $account->id,
            'charges_enabled'      => $account->charges_enabled,
            'onboarding_completed' => $account->details_submitted,
        ]);
    }
}
