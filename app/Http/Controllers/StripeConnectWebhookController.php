<?php

namespace App\Http\Controllers;

use App\Models\StripeConnectAccount;
use App\Models\Tenant;
use App\Services\ClientPortal\PortalInvoicePaymentService;
use App\Services\StripeConnectService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;

/**
 * The canonical Stripe *Connect* webhook (Step 6A, consolidated in 6A.1):
 * POST /stripe/connect/webhook on the central domain. Stripe delivers
 * events from ALL connected accounts to this one URL - the old
 * tenant-domain /connect/webhook could only ever see one tenant's database
 * and has been retired.
 *
 * Fails closed: no STRIPE_CONNECT_WEBHOOK_SECRET -> every event refused;
 * bad signature -> 400. Nothing is read from the payload before the
 * signature is verified.
 *
 * Tenant resolution never scans tenant databases:
 *  - invoice payments (portal / pay link): metadata.tenant_id, only a
 *    pointer - the session must then match a payment attempt WE recorded
 *    in that tenant (PortalInvoicePaymentService::handleSessionEvent());
 *  - legacy /pay sessions created before 6A.1: metadata.tenant_id, then the
 *    session id must equal the one the old code stored on the invoice
 *    (PortalInvoicePaymentService::settleLegacySession());
 *  - account.updated: the signed event's `account` -> the central
 *    stripe_connect_accounts map (written only by StripeConnectService).
 */
class StripeConnectWebhookController extends Controller
{
    private const SESSION_EVENTS = [
        'checkout.session.completed',
        'checkout.session.async_payment_succeeded',
        'checkout.session.async_payment_failed',
        'checkout.session.expired',
    ];

    public function handle(Request $request, PortalInvoicePaymentService $payments, StripeConnectService $connect)
    {
        $secret = config('services.stripe.connect_webhook_secret');

        if (empty($secret)) {
            Log::error('Stripe Connect webhook: STRIPE_CONNECT_WEBHOOK_SECRET is not configured - event refused');
            return response('Webhook not configured', 503);
        }

        try {
            $event = Webhook::constructEvent($request->getContent(), (string) $request->header('Stripe-Signature'), $secret);
        } catch (\Throwable $e) {
            Log::warning('Stripe Connect webhook: signature verification failed', ['error' => $e->getMessage()]);
            return response('Invalid signature', 400);
        }

        $object  = $event->data->object ?? null;
        $account = $event->account ?? null;

        if ($event->type === 'account.updated') {
            $this->onAccountUpdated($object, $account, $connect);
        } elseif (in_array($event->type, self::SESSION_EVENTS, true)) {
            $this->onSessionEvent($event->type, $object, $account, $payments);
        } else {
            Log::info('Stripe Connect webhook: event ignored', ['type' => $event->type]);
        }

        return response('OK', 200);
    }

    private function onSessionEvent(string $type, object $session, ?string $account, PortalInvoicePaymentService $payments): void
    {
        $purpose = $session->metadata->purpose ?? null;

        if (in_array($purpose, PortalInvoicePaymentService::PURPOSES, true)) {
            $this->inTenant($session->metadata->tenant_id ?? null, fn () => $payments->handleSessionEvent($type, $session, $account));
            return;
        }

        // Pre-6A.1 /pay session (no purpose). Only a completed payment can
        // matter; nothing else about it needs recording.
        if ($purpose === null
            && in_array($type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)
            && !empty($session->metadata->invoice_uuid)) {
            $this->inTenant($session->metadata->tenant_id ?? null, fn () => $payments->settleLegacySession($session, $account));
        }
    }

    private function onAccountUpdated(object $accountObject, ?string $eventAccount, StripeConnectService $connect): void
    {
        // For a Connect event the signed `account` IS the account the event
        // is about; the object must agree with it.
        if (!$eventAccount || ($accountObject->id ?? null) !== $eventAccount) {
            Log::warning('Stripe Connect account.updated: event account does not match the payload', [
                'event_account'  => $eventAccount,
                'payload_account'=> $accountObject->id ?? null,
            ]);
            return;
        }

        $mapping = StripeConnectAccount::where('stripe_account_id', $eventAccount)->first();

        if (!$mapping) {
            Log::info('Stripe Connect account.updated: account not linked to any tenant', ['account_id' => $eventAccount]);
            return;
        }

        $this->inTenant($mapping->tenant_id, function () use ($connect, $accountObject, $eventAccount) {
            if (!$connect->applyAccountUpdate($accountObject)) {
                Log::warning('Stripe Connect account.updated: tenant profile no longer holds this account', ['account_id' => $eventAccount]);
            }
        });
    }

    private function inTenant(?string $tenantId, callable $callback): void
    {
        $tenant = $tenantId ? Tenant::find($tenantId) : null;

        if (!$tenant) {
            Log::warning('Stripe Connect webhook: tenant not found', ['tenant_id' => $tenantId]);
            return;
        }

        tenancy()->initialize($tenant);

        try {
            $callback();
        } finally {
            tenancy()->end();
        }
    }
}
