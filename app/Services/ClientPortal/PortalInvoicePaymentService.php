<?php

namespace App\Services\ClientPortal;

use App\Models\CompanyProfile;
use App\Models\Invoice;
use App\Models\InvoiceHistory;
use App\Models\InvoicePaymentAttempt;
use App\Services\StripeConnectService;
use App\Services\TenantContextService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Stripe;

/**
 * Client Portal Step 6A - customer pays an issued invoice through the
 * seller's own Stripe Connect account.
 *
 * Reuses the existing Connect architecture: the seller's connected
 * account (CompanyProfile::stripe_account_id, gated by
 * StripeConnectService::canAcceptPayments()) and a Checkout Session
 * created ON that account via the `stripe_account` request option - the
 * same "direct charge" strategy StripeConnectService::createConnectedCheckoutSession()
 * uses. No application fee: none exists for invoice payments today.
 *
 * Nothing that decides what is charged ever comes from the browser:
 * amount = the invoice's stored total, currency = TenantContextService::currency(),
 * account = the tenant's own CompanyProfile.
 */
class PortalInvoicePaymentService
{
    /** Stamped into Checkout metadata; only the central Connect webhook settles these. */
    public const PURPOSE          = 'client_portal_invoice';
    /** Step 6A.1 - the /pay/{uuid} link (emails, PDFs) on the same architecture. */
    public const PURPOSE_PAY_LINK = 'invoice_pay_link';
    public const PURPOSES         = [self::PURPOSE, self::PURPOSE_PAY_LINK];

    /**
     * Two-decimal currencies Fakturalista actually invoices in (Spain,
     * Morocco). The amount is sent to Stripe as total * 100, which would be
     * wrong for a zero/three-decimal currency - so nothing else is allowed.
     */
    public const SUPPORTED_CURRENCIES = ['EUR', 'MAD'];

    /** Reuse an open session only if it still has at least this long to live. */
    private const REUSE_MARGIN_MINUTES = 10;

    public function __construct(
        private StripeConnectService $connect,
        private TenantContextService $tenantContext,
    ) {
    }

    // ── Eligibility ───────────────────────────────────────────────────

    /**
     * Throws PortalPaymentException if this invoice can't be paid online
     * right now. Ownership/visibility is checked by the caller
     * (ClientPortalController) before this is ever reached.
     */
    public function assertPayable(Invoice $invoice): void
    {
        $this->assertInvoicePayable($invoice);
        $this->assertSellerAcceptsPayments();
    }

    /**
     * Step 6B - the portal's per-invoice `payment` capability. Same rules
     * as assertPayable() (the Checkout endpoint stays authoritative); the
     * seller-level half is passed in so a list of invoices checks the
     * seller's Stripe setup once, not once per invoice.
     */
    public function isPayable(Invoice $invoice, bool $sellerAcceptsPayments): bool
    {
        if (!$sellerAcceptsPayments) {
            return false;
        }

        try {
            $this->assertInvoicePayable($invoice);
            return true;
        } catch (PortalPaymentException) {
            return false;
        }
    }

    public function sellerAcceptsPayments(): bool
    {
        try {
            $this->assertSellerAcceptsPayments();
            return true;
        } catch (PortalPaymentException) {
            return false;
        }
    }

    private function assertInvoicePayable(Invoice $invoice): void
    {
        if ($invoice->isPaid()) {
            throw new PortalPaymentException('already_paid', 422);
        }

        if (!$invoice->isIssued()) {
            // draft / cancelled / anything else
            throw new PortalPaymentException('not_payable', 422);
        }

        if ($this->amountMinor($invoice) <= 0) {
            throw new PortalPaymentException('not_payable', 422);
        }
    }

    private function assertSellerAcceptsPayments(): void
    {
        if (!in_array($this->currency(), self::SUPPORTED_CURRENCIES, true)) {
            throw new PortalPaymentException('currency_unsupported', 422);
        }

        if (empty(config('services.stripe.secret')) || !$this->connect->canAcceptPayments($this->profile())) {
            throw new PortalPaymentException('unavailable', 503);
        }
    }

    public function amountMinor(Invoice $invoice): int
    {
        return (int) round(((float) ($invoice->total ?? 0)) * 100);
    }

    public function currency(): string
    {
        return strtoupper($this->tenantContext->currency());
    }

    // ── Checkout ──────────────────────────────────────────────────────

    /**
     * Returns the Checkout URL to redirect the customer to - an existing
     * still-open session for the exact same charge if there is one
     * (double clicks / repeated requests), otherwise a new one.
     *
     * Serialized per invoice by a row lock, so two concurrent requests can
     * never both create a session.
     *
     * @throws PortalPaymentException
     */
    public function startCheckout(Invoice $invoice, string $successUrl, string $cancelUrl, string $purpose = self::PURPOSE): string
    {
        if (!in_array($purpose, self::PURPOSES, true)) {
            throw new \InvalidArgumentException("Unknown payment purpose [$purpose].");
        }

        return DB::transaction(function () use ($invoice, $successUrl, $cancelUrl, $purpose) {
            $invoice = Invoice::whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();

            $this->assertPayable($invoice);

            $accountId = $this->profile()->stripe_account_id;
            $amount    = $this->amountMinor($invoice);
            $currency  = $this->currency();

            $open = InvoicePaymentAttempt::where('invoice_id', $invoice->id)
                ->where('status', InvoicePaymentAttempt::STATUS_OPEN)
                ->get();

            // Only ever reuse a session created for the same flow: its
            // success/cancel URLs belong to that flow (a portal session's
            // URLs carry the portal token).
            $reusable = $open->first(fn (InvoicePaymentAttempt $a) =>
                $a->purpose === $purpose
                && $a->stripe_account_id === $accountId
                && $a->amount_minor === $amount
                && $a->currency === $currency
                && $a->expires_at?->gt(now()->addMinutes(self::REUSE_MARGIN_MINUTES))
                && $a->checkout_url
            );

            if ($reusable) {
                return $reusable->checkout_url;
            }

            // Never leave a second payable session open for the same
            // invoice: close stale ones first. If Stripe won't expire one
            // (e.g. the customer is completing it right now), don't open
            // another - the webhook will settle the first.
            foreach ($open as $stale) {
                $this->expireSession($stale);
            }

            $attempt = InvoicePaymentAttempt::create([
                'uuid'              => (string) Str::uuid(),
                'invoice_id'        => $invoice->id,
                'provider'          => 'stripe',
                'purpose'           => $purpose,
                'stripe_account_id' => $accountId,
                'amount_minor'      => $amount,
                'currency'          => $currency,
                'status'            => InvoicePaymentAttempt::STATUS_PENDING,
            ]);

            $session = $this->createSession($invoice, $attempt, $successUrl, $cancelUrl);

            $attempt->update([
                'stripe_session_id'        => $session->id,
                'stripe_payment_intent_id' => is_string($session->payment_intent ?? null) ? $session->payment_intent : null,
                'checkout_url'             => $session->url,
                'expires_at'               => isset($session->expires_at) ? now()->setTimestamp($session->expires_at) : now()->addHours(23),
                'status'                   => InvoicePaymentAttempt::STATUS_OPEN,
            ]);

            return $session->url;
        });
    }

    private function createSession(Invoice $invoice, InvoicePaymentAttempt $attempt, string $successUrl, string $cancelUrl): StripeSession
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        $profile     = $this->profile();
        $companyName = $profile->trade_name ?: $profile->legal_name ?: config('app.name');

        // Same tenant-locale document word as PaymentController::createSession().
        $documentWord = match ($this->tenantContext->locale()) {
            'fr'    => 'Facture',
            'es'    => 'Factura',
            default => 'Invoice',
        };

        // Identifiers only - no names, emails, amounts or other personal data.
        $metadata = [
            'purpose'      => $attempt->purpose,
            'tenant_id'    => (string) (tenancy()->tenant?->getTenantKey() ?? ''),
            'invoice_uuid' => $invoice->uuid,
            'attempt_uuid' => $attempt->uuid,
        ];

        return StripeSession::create(
            [
                'mode'                 => 'payment',
                'payment_method_types' => ['card'],
                'line_items'           => [[
                    'price_data' => [
                        'currency'     => strtolower($attempt->currency),
                        'unit_amount'  => $attempt->amount_minor,
                        'product_data' => [
                            'name'        => $documentWord . ' ' . $invoice->displayNumber(),
                            'description' => $companyName,
                        ],
                    ],
                    'quantity' => 1,
                ]],
                'client_reference_id' => $attempt->uuid,
                'metadata'            => $metadata,
                'payment_intent_data' => ['metadata' => $metadata],
                'success_url'         => $successUrl,
                'cancel_url'          => $cancelUrl,
                'expires_at'          => now()->addHours(23)->timestamp,
            ],
            [
                // Direct charge on the seller's connected account - funds
                // never touch Fakturalista's own Stripe balance.
                'stripe_account'  => $attempt->stripe_account_id,
                // One attempt = one Stripe session, even if the SDK or a
                // proxy retries this exact request.
                'idempotency_key' => 'portal-invoice-' . $attempt->uuid,
            ]
        );
    }

    /**
     * @throws PortalPaymentException when the session can't be closed
     */
    private function expireSession(InvoicePaymentAttempt $attempt): void
    {
        if (!$attempt->stripe_session_id) {
            $attempt->update(['status' => InvoicePaymentAttempt::STATUS_EXPIRED]);
            return;
        }

        try {
            Stripe::setApiKey(config('services.stripe.secret'));
            $remote = StripeSession::retrieve($attempt->stripe_session_id, ['stripe_account' => $attempt->stripe_account_id]);

            if (($remote->status ?? null) === 'complete') {
                // Paid (or being paid) - the webhook settles it.
                throw new PortalPaymentException('in_progress', 409);
            }

            if (($remote->status ?? null) !== 'expired') {
                $remote->expire(null, ['stripe_account' => $attempt->stripe_account_id]);
            }
        } catch (PortalPaymentException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::warning('Client Portal: could not expire previous Checkout Session', [
                'attempt_uuid' => $attempt->uuid,
                'error'        => $e->getMessage(),
            ]);
            throw new PortalPaymentException('in_progress', 409);
        }

        $attempt->update(['status' => InvoicePaymentAttempt::STATUS_EXPIRED]);
    }

    // ── Webhook ───────────────────────────────────────────────────────

    /**
     * Applies one verified Stripe Checkout event (the caller has already
     * checked the signature and initialized the right tenant). Idempotent:
     * every branch is a no-op once the attempt has left STATUS_OPEN, so a
     * redelivered event never has a second side effect.
     *
     * The event's own content is only trusted where it matches what we
     * recorded when creating the session: session id, attempt uuid,
     * connected account, amount and currency must all agree.
     */
    public function handleSessionEvent(string $type, object $session, ?string $eventAccount): void
    {
        DB::transaction(function () use ($type, $session, $eventAccount) {
            $attempt = InvoicePaymentAttempt::where('stripe_session_id', (string) ($session->id ?? ''))
                ->lockForUpdate()
                ->first();

            $context = ['session_id' => $session->id ?? null, 'type' => $type];

            if (!$attempt) {
                Log::warning('Client Portal payment webhook: unknown session', $context);
                return;
            }

            $context['attempt_uuid'] = $attempt->uuid;

            if (($session->metadata->attempt_uuid ?? null) !== $attempt->uuid
                || $eventAccount === null
                || $eventAccount !== $attempt->stripe_account_id) {
                Log::critical('Client Portal payment webhook: session/account does not match the recorded attempt', $context + [
                    'event_account' => $eventAccount,
                ]);
                return;
            }

            if ($attempt->status !== InvoicePaymentAttempt::STATUS_OPEN) {
                return; // already settled - duplicate or out-of-order event
            }

            switch ($type) {
                case 'checkout.session.expired':
                    $attempt->update(['status' => InvoicePaymentAttempt::STATUS_EXPIRED]);
                    return;

                case 'checkout.session.async_payment_failed':
                    $attempt->update(['status' => InvoicePaymentAttempt::STATUS_FAILED, 'failure_reason' => 'payment_failed']);
                    return;

                case 'checkout.session.completed':
                case 'checkout.session.async_payment_succeeded':
                    // `completed` also fires for delayed methods before the
                    // money has actually arrived - wait for async_payment_succeeded.
                    if (($session->payment_status ?? null) !== 'paid') {
                        return;
                    }
                    $this->settlePaidAttempt($attempt, $session, $context);
                    return;
            }
        });
    }

    private function settlePaidAttempt(InvoicePaymentAttempt $attempt, object $session, array $context): void
    {
        $invoice = Invoice::whereKey($attempt->invoice_id)->lockForUpdate()->first();

        $paidAmount   = (int) ($session->amount_total ?? -1);
        $paidCurrency = strtoupper((string) ($session->currency ?? ''));

        $reason = match (true) {
            $paidAmount !== $attempt->amount_minor                           => 'amount_mismatch',
            $paidCurrency !== $attempt->currency                             => 'currency_mismatch',
            !$invoice                                                        => 'invoice_missing',
            $this->amountMinor($invoice) !== $attempt->amount_minor          => 'amount_mismatch',
            $this->currency() !== $attempt->currency                         => 'currency_mismatch',
            $invoice->isPaid()                                               => 'invoice_already_paid',
            !$invoice->isIssued()                                            => 'invoice_not_payable',
            default                                                          => null,
        };

        $intentId = is_string($session->payment_intent ?? null) ? $session->payment_intent : $attempt->stripe_payment_intent_id;

        if ($reason !== null) {
            // Money may have moved but the invoice is NOT marked paid -
            // left for the seller to review/refund in their Stripe account.
            $attempt->update([
                'status'                   => InvoicePaymentAttempt::STATUS_REJECTED,
                'failure_reason'           => $reason,
                'stripe_payment_intent_id' => $intentId,
                'completed_at'             => now(),
            ]);
            Log::critical('Client Portal payment webhook: paid session NOT applied to invoice', $context + [
                'reason'        => $reason,
                'paid_amount'   => $paidAmount,
                'paid_currency' => $paidCurrency,
            ]);
            return;
        }

        $this->markInvoicePaid($invoice, $session, [
            'source'       => $attempt->purpose === self::PURPOSE ? 'client_portal' : 'pay_link',
            'attempt_uuid' => $attempt->uuid,
        ]);

        $attempt->update([
            'status'                   => InvoicePaymentAttempt::STATUS_PAID,
            'stripe_payment_intent_id' => $intentId,
            'completed_at'             => now(),
        ]);

        Log::info('Client Portal: invoice paid via Stripe Connect', $context + ['invoice_uuid' => $invoice->uuid]);
    }

    /**
     * Step 6A.1 - a Checkout Session created by the OLD /pay code (before
     * this deploy: no `purpose`, no attempt row) that completes afterwards.
     * Sessions live at most 24h, so this only matters during the rollover.
     *
     * Trust anchor: the old code stored the session id on the invoice
     * itself (invoices.stripe_session_id) when it created the session, so
     * a session is only accepted if it is exactly that one - plus paid,
     * right amount, right currency and, for a connected-account event, the
     * tenant's own connected account. Must run inside the tenant.
     */
    public function settleLegacySession(object $session, ?string $eventAccount): void
    {
        DB::transaction(function () use ($session, $eventAccount) {
            $context = ['session_id' => $session->id ?? null, 'legacy' => true];

            $invoice = Invoice::where('uuid', (string) ($session->metadata->invoice_uuid ?? ''))
                ->lockForUpdate()
                ->first();

            if ($invoice?->isPaid()) {
                return; // duplicate delivery / already settled
            }

            $reason = match (true) {
                !$invoice                                                          => 'invoice_missing',
                empty($invoice->stripe_session_id)
                    || !hash_equals((string) $invoice->stripe_session_id, (string) ($session->id ?? '')) => 'unknown_session',
                ($session->payment_status ?? null) !== 'paid'                      => 'not_paid',
                $eventAccount !== null
                    && $eventAccount !== $this->profile()->stripe_account_id      => 'account_mismatch',
                (int) ($session->amount_total ?? -1) !== $this->amountMinor($invoice) => 'amount_mismatch',
                strtoupper((string) ($session->currency ?? '')) !== $this->currency()  => 'currency_mismatch',
                !$invoice->isIssued()                                              => 'invoice_not_payable',
                default                                                            => null,
            };

            if ($reason !== null) {
                $level = $reason === 'not_paid' ? 'info' : 'critical';
                Log::$level('Legacy Stripe invoice session NOT applied', $context + ['reason' => $reason]);
                return;
            }

            $this->markInvoicePaid($invoice, $session, ['source' => 'pay_link_legacy']);
        });
    }

    /**
     * Same fields and history entry as every existing "mark paid" path
     * (InvoicePaymentsController::record(), the previous Stripe webhooks).
     * stripe_session_id is also kept as the invoice's transaction
     * reference, as the payments list (InvoicePaymentsController) shows it.
     */
    private function markInvoicePaid(Invoice $invoice, object $session, array $history): void
    {
        $invoice->status            = Invoice::STATUS_PAID;
        $invoice->paid_at           = now();
        $invoice->paid_via          = 'stripe';
        $invoice->stripe_session_id = $session->id ?? $invoice->stripe_session_id;
        $invoice->save();

        $invoice->logHistory(InvoiceHistory::ACTION_PAID, [
            'via'        => 'stripe',
            'session_id' => $session->id ?? null,
        ] + $history);
    }

    private function profile(): CompanyProfile
    {
        return $this->tenantContext->ensureCompanyProfile();
    }
}
