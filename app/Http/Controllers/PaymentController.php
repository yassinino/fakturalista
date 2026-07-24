<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use App\Models\Invoice;
use App\Models\InvoiceHistory;
use App\Services\StripeConnectService;
use Illuminate\Http\Request;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;

class PaymentController extends Controller
{
    // ── Helpers ───────────────────────────────────────────────────────

    private function initStripe(): void
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    private function isStripeConfigured(): bool
    {
        return !empty(config('services.stripe.secret'));
    }

    private function stablePayUrl(Invoice $invoice): string
    {
        return request()->getSchemeAndHttpHost() . '/pay/' . $invoice->uuid;
    }

    /**
     * Create a Stripe Checkout Session.
     *
     * If the tenant has a fully connected and enabled Stripe account (via Connect),
     * the payment goes directly to that account.
     * If not, fall back to the platform key (funds go to platform - legacy behaviour).
     *
     * @throws ApiErrorException
     */
    private function createSession(Invoice $invoice): StripeSession
    {
        $this->initStripe();

        $company     = CompanyProfile::firstOrCreate([], ['legal_name' => '']);
        $companyName = $company->trade_name ?: $company->legal_name ?: config('app.name');
        $currency    = strtolower($company->currency ?: 'eur');
        $host        = request()->getSchemeAndHttpHost();

        $sessionData = [
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
            'success_url' => $host . '/pay/' . $invoice->uuid . '/success',
            'cancel_url'  => $host . '/pay/' . $invoice->uuid . '/cancel',
            'expires_at'  => now()->addHours(23)->timestamp,
        ];

        // Route to the connected account if available and able to charge.
        // This is the core of Stripe Connect - the `stripe_account` option
        // tells Stripe to execute this API call on behalf of the connected account,
        // so the payment goes directly into their balance.
        $requestOptions = [];
        $connectService = app(StripeConnectService::class);
        if ($connectService->canAcceptPayments($company)) {
            $requestOptions['stripe_account'] = $company->stripe_account_id;
        }

        $session = StripeSession::create($sessionData, $requestOptions ?: null);

        $invoice->update([
            'stripe_session_id'         => $session->id,
            'stripe_payment_url'        => $session->url,
            'stripe_session_expires_at' => now()->addHours(23),
        ]);

        return $session;
    }

    private function sessionIsValid(Invoice $invoice): bool
    {
        return $invoice->stripe_session_id !== null
            && $invoice->stripe_session_expires_at !== null
            && $invoice->stripe_session_expires_at->gt(now()->addMinutes(10));
    }

    // ── Authenticated: admin creates / retrieves a payment link ───────

    public function getOrCreatePaymentLink(Invoice $invoice)
    {
        if (!$invoice->isIssued()) {
            return response()->json([
                'message' => 'Only issued invoices can generate a payment link.',
            ], 422);
        }

        if (!$this->isStripeConfigured()) {
            return response()->json([
                'message' => 'Stripe is not configured on this account.',
            ], 422);
        }

        // Check that the Connect account is ready to accept payments
        $company        = CompanyProfile::firstOrCreate([], ['legal_name' => '']);
        $connectService = app(StripeConnectService::class);

        if (!$connectService->canAcceptPayments($company)) {
            $message = empty($company->stripe_account_id)
                ? 'Connect your Stripe account in Settings to generate payment links.'
                : 'Your Stripe account setup is incomplete. Finish onboarding in Settings.';

            return response()->json(['message' => $message], 422);
        }

        if ($this->sessionIsValid($invoice)) {
            return response()->json([
                'payment_url' => $this->stablePayUrl($invoice),
            ]);
        }

        try {
            $this->createSession($invoice);
        } catch (ApiErrorException $e) {
            return response()->json(['message' => 'Stripe error: ' . $e->getMessage()], 502);
        }

        return response()->json([
            'payment_url' => $this->stablePayUrl($invoice),
        ]);
    }

    // ── Public: client lands here from PDF / email / WhatsApp ─────────

    public function publicPayPage(string $uuid)
    {
        $invoice = Invoice::where('uuid', $uuid)->first();

        if (!$invoice) {
            abort(404);
        }

        if ($invoice->isPaid()) {
            return view('payment.paid', compact('invoice'));
        }

        if ($invoice->isCancelled()) {
            return view('payment.cancel', ['invoice' => $invoice, 'cancelled' => true]);
        }

        if (!$this->isStripeConfigured()) {
            abort(503, 'Online payment is not available.');
        }

        $company        = CompanyProfile::firstOrCreate([], ['legal_name' => '']);
        $connectService = app(StripeConnectService::class);

        if (!$connectService->canAcceptPayments($company)) {
            abort(503, 'Online payment is not currently available for this account.');
        }

        // Use cached session if still valid; otherwise create a fresh one
        if ($this->sessionIsValid($invoice) && $invoice->stripe_payment_url) {
            return redirect($invoice->stripe_payment_url);
        }

        try {
            $session = $this->createSession($invoice);
            return redirect($session->url);
        } catch (ApiErrorException $e) {
            abort(503, 'Unable to initiate payment. Please try again later.');
        }
    }

    public function paymentSuccess(string $uuid)
    {
        $invoice = Invoice::where('uuid', $uuid)->first();
        return view('payment.success', compact('invoice'));
    }

    public function paymentCancel(string $uuid)
    {
        $invoice = Invoice::where('uuid', $uuid)->first();
        return view('payment.cancel', ['invoice' => $invoice, 'cancelled' => false]);
    }

    // ── Tenant-level Stripe webhook ───────────────────────────────────

    /**
     * Handles invoice payment webhook events from the tenant's Stripe account.
     * This fires when the Checkout Session is on the platform key (legacy / fallback).
     *
     * For Connect payments, events are handled by StripeConnectController::handleWebhook.
     */
    public function stripeWebhook(Request $request)
    {
        $payload   = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret    = config('services.stripe.webhook_secret');

        try {
            $event = $secret
                ? \Stripe\Webhook::constructEvent($payload, $sigHeader, $secret)
                : json_decode($payload);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            return response('Invalid signature', 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $session     = $event->data->object;
            $invoiceUuid = $session->metadata->invoice_uuid ?? null;

            if (!$invoiceUuid) {
                return response('OK', 200);
            }

            $invoice = Invoice::where('uuid', $invoiceUuid)->first();

            if ($invoice && !$invoice->isPaid()) {
                $invoice->update([
                    'status'  => Invoice::STATUS_PAID,
                    'paid_at' => now(),
                    'paid_via'=> 'stripe',
                ]);
                $invoice->logHistory(InvoiceHistory::ACTION_PAID, [
                    'via'        => 'stripe',
                    'session_id' => $session->id,
                ]);
            }
        }

        return response('OK', 200);
    }
}
