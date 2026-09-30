<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\ClientPortal\PortalInvoicePaymentService;
use App\Services\ClientPortal\PortalPaymentException;
use App\Services\StripeConnectService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;

/**
 * The /pay/{uuid} invoice payment link (emails, PDFs, WhatsApp).
 *
 * Step 6A.1: Checkout Sessions are created by the same
 * PortalInvoicePaymentService the Client Portal uses (purpose
 * PURPOSE_PAY_LINK) - always on the seller's connected account, amount
 * and currency decided server-side, settled only by the verified central
 * Connect webhook. The old createSession() fallback that charged on
 * Fakturalista's OWN Stripe account when the seller's Connect account
 * wasn't ready is gone: without a usable Connect account a payment now
 * fails safely instead.
 */
class PaymentController extends Controller
{
    // ── Helpers ───────────────────────────────────────────────────────

    private function isStripeConfigured(): bool
    {
        return !empty(config('services.stripe.secret'));
    }

    private function stablePayUrl(Invoice $invoice): string
    {
        return request()->getSchemeAndHttpHost() . '/pay/' . $invoice->uuid;
    }

    private function startCheckout(Invoice $invoice): string
    {
        return app(PortalInvoicePaymentService::class)->startCheckout(
            $invoice,
            $this->stablePayUrl($invoice) . '/success',
            $this->stablePayUrl($invoice) . '/cancel',
            PortalInvoicePaymentService::PURPOSE_PAY_LINK,
        );
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
        $company        = app(\App\Services\TenantContextService::class)->ensureCompanyProfile();
        $connectService = app(StripeConnectService::class);

        if (!$connectService->canAcceptPayments($company)) {
            $message = empty($company->stripe_account_id)
                ? 'Connect your Stripe account in Settings to generate payment links.'
                : 'Your Stripe account setup is incomplete. Finish onboarding in Settings.';

            return response()->json(['message' => $message], 422);
        }

        // Prepares (or reuses) the Checkout Session up front, as before, so
        // a Stripe problem surfaces here rather than to the customer.
        try {
            $this->startCheckout($invoice);
        } catch (PortalPaymentException $e) {
            return response()->json(['message' => __('invoice.portal_payment.' . $e->reason)], $e->status === 503 ? 422 : $e->status);
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

        try {
            return redirect($this->startCheckout($invoice));
        } catch (PortalPaymentException $e) {
            return match ($e->reason) {
                'already_paid' => view('payment.paid', compact('invoice')),
                'not_payable'  => abort(404),
                'in_progress'  => abort(409, 'A payment is already in progress for this invoice. Please try again in a few minutes.'),
                default        => abort(503, 'Online payment is not currently available for this account.'),
            };
        } catch (\Throwable $e) {
            Log::error('/pay Checkout creation failed', ['invoice_uuid' => $invoice->uuid, 'error' => $e->getMessage()]);
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
     * Legacy tenant-domain PLATFORM-account webhook (/payment/webhook).
     * Nothing creates seller-invoice sessions on the platform account any
     * more (Step 6A.1); this stays only so a session from the old fallback
     * completing during the rollover is still settled - and, like
     * everything else now, only after signature verification and
     * PortalInvoicePaymentService::settleLegacySession()'s checks.
     */
    public function stripeWebhook(Request $request)
    {
        $secret = config('services.stripe.webhook_secret');

        if (empty($secret)) {
            Log::error('Tenant payment webhook: STRIPE_WEBHOOK_SECRET is not configured - event refused');
            return response('Webhook not configured', 503);
        }

        try {
            $event = \Stripe\Webhook::constructEvent($request->getContent(), (string) $request->header('Stripe-Signature'), $secret);
        } catch (\Throwable $e) {
            return response('Invalid signature', 400);
        }

        if (in_array($event->type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
            $session = $event->data->object;

            // Portal / pay-link attempts are settled only by the central
            // Connect webhook; a session for another tenant is never
            // touched from this tenant's domain.
            if (!empty($session->metadata->invoice_uuid)
                && empty($session->metadata->purpose)
                && ($session->metadata->tenant_id ?? null) === tenancy()->tenant?->getTenantKey()) {
                app(PortalInvoicePaymentService::class)->settleLegacySession($session, $event->account ?? null);
            }
        }

        return response('OK', 200);
    }
}
