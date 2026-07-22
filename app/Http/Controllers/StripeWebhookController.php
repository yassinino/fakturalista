<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceHistory;
use App\Models\Plan;
use App\Models\BillingProfile;
use App\Models\Subscription;
use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Webhook;
use Stripe\Subscription as StripeSubscription;
use Stripe\Invoice as StripeInvoice;
use Carbon\Carbon;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        $payload   = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret    = config('services.stripe.webhook_secret');

        try {
            if ($secret) {
                $event = Webhook::constructEvent($payload, $sigHeader, $secret);
            } else {
                // pour tests sans signature (à éviter en prod)
                $event = json_decode($payload);
            }
        } catch (\Exception $e) {
            Log::error('Stripe webhook signature error', [
                'error' => $e->getMessage(),
            ]);

            return response('Invalid signature', 400);
        }

        $type = $event->type ?? null;

        switch ($type) {
            case 'checkout.session.completed':
                // Route to invoice payment handler if invoice_uuid is in metadata
                if (!empty($event->data->object->metadata->invoice_uuid)) {
                    $this->handleInvoicePayment($event->data->object);
                } else {
                    $this->handleCheckoutSessionCompleted($event->data->object);
                }
                break;

            case 'invoice.payment_succeeded':
                $this->handleInvoicePaymentSucceeded($event->data->object);
                break;

            case 'customer.subscription.updated':
                $this->handleSubscriptionUpdated($event->data->object);
                break;

            case 'customer.subscription.deleted':
                $this->handleSubscriptionDeleted($event->data->object);
                break;

            default:
                Log::info('Stripe webhook event ignored', ['type' => $type]);
                break;
        }

        return response('OK', 200);
    }

    /**
     * 1️⃣ Premier paiement: on crée/maj l'abonnement central.
     *    (on ne gère pas le Payment ici, on le centralise dans invoice.payment_succeeded)
     */
    /**
     * Handle invoice payment completion.
     * Bootstraps the correct tenant DB context from metadata, then marks the invoice paid.
     */
    protected function handleInvoicePayment($session): void
    {
        $invoiceUuid = $session->metadata->invoice_uuid ?? null;
        $tenantId    = $session->metadata->tenant_id    ?? null;

        if (!$invoiceUuid || !$tenantId) {
            Log::warning('Invoice payment webhook: missing metadata', [
                'invoice_uuid' => $invoiceUuid,
                'tenant_id'    => $tenantId,
            ]);
            return;
        }

        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            Log::error('Invoice payment webhook: tenant not found', ['tenant_id' => $tenantId]);
            return;
        }

        tenancy()->initialize($tenant);

        try {
            $invoice = Invoice::where('uuid', $invoiceUuid)->first();

            if (!$invoice) {
                Log::warning('Invoice payment webhook: invoice not found', ['uuid' => $invoiceUuid]);
                return;
            }

            if ($invoice->isPaid()) {
                return; // idempotent — already handled
            }

            $invoice->update([
                'status'  => Invoice::STATUS_PAID,
                'paid_at' => now(),
                'paid_via'=> 'stripe',
            ]);

            $invoice->logHistory(InvoiceHistory::ACTION_PAID, [
                'via'        => 'stripe',
                'session_id' => $session->id,
            ]);

            Log::info('Invoice marked paid via Stripe', [
                'invoice_uuid' => $invoiceUuid,
                'tenant_id'    => $tenantId,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error in handleInvoicePayment', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);
        } finally {
            tenancy()->end();
        }
    }

    /**
     * 1️⃣ Premier paiement: on crée/maj l'abonnement central.
     *    (on ne gère pas le Payment ici, on le centralise dans invoice.payment_succeeded)
     */
    protected function handleCheckoutSessionCompleted($session)
    {
        Log::info('✅ handleCheckoutSessionCompleted called', [
            'session_id' => $session->id ?? null,
            'metadata'   => $session->metadata ?? null,
        ]);

        try {
            $tenantId       = $session->metadata->tenant_id ?? null;
            $planId         = $session->metadata->plan_id ?? null;
            $subscriptionId = $session->subscription ?? null; // id Stripe
            $customerId     = $session->customer ?? null;

            if (! $tenantId || ! $planId || ! $subscriptionId) {
                Log::warning('❗ Missing metadata, abort checkout.session.completed', [
                    'tenant_id'       => $tenantId,
                    'plan_id'         => $planId,
                    'subscription_id' => $subscriptionId,
                ]);
                return;
            }

            // Récupérer le plan
            $plan = Plan::where('id', $planId)
                ->where('active', true)
                ->first();

            if (! $plan) {
                Log::error('❗ Plan not found in webhook', ['plan_id' => $planId]);
                return;
            }

            // Récupérer la subscription Stripe
            $stripeSub = StripeSubscription::retrieve($subscriptionId);

            // Billing profile (tenant + stripe customer)
            $billingProfile = BillingProfile::firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'provider'  => 'stripe',
                ],
                [
                    'email' => $session->customer_details->email ?? null,
                ]
            );

            if (! $billingProfile->provider_customer_id && $customerId) {
                $billingProfile->update([
                    'provider_customer_id' => $customerId,
                ]);
            }

            // Abonnement central
            $subscription = Subscription::updateOrCreate(
                [
                    'tenant_id'                => $tenantId,
                    'provider'                 => 'stripe',
                    'provider_subscription_id' => $stripeSub->id,
                ],
                [
                    'plan_id'               => $plan->id,
                    'status'                => $stripeSub->status,
                    'trial_ends_at'         => $stripeSub->trial_end
                        ? Carbon::createFromTimestamp($stripeSub->trial_end)
                        : null,
                    'current_period_ends_at' => $stripeSub->current_period_end
                        ? Carbon::createFromTimestamp($stripeSub->current_period_end)
                        : null,
                ]
            );

            // Sync the denormalized subscription_status on the Tenant row so
            // the paywall middleware can check it without joining subscriptions.
            $tenant = Tenant::find($tenantId);
            $tenant?->syncSubscriptionStatus($stripeSub->status);

            Log::info('🎉 Subscription saved/updated', [
                'subscription_id' => $subscription->id,
                'tenant_id'       => $tenantId,
            ]);

        } catch (\Throwable $e) {
            Log::error('💥 Error in handleCheckoutSessionCompleted', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);
        }
    }

    /**
     * Sync Tenant.subscription_status when a subscription changes (e.g. renewal, past_due).
     */
    protected function handleSubscriptionUpdated(object $stripeSub): void
    {
        $tenantId = $this->resolveTenantIdFromSub($stripeSub);
        if (!$tenantId) {
            return;
        }

        Subscription::where('provider', 'stripe')
            ->where('provider_subscription_id', $stripeSub->id)
            ->update([
                'status'                 => $stripeSub->status,
                'current_period_ends_at' => $stripeSub->current_period_end
                    ? Carbon::createFromTimestamp($stripeSub->current_period_end)
                    : null,
            ]);

        $tenant = Tenant::find($tenantId);
        $tenant?->syncSubscriptionStatus($stripeSub->status);

        Log::info('Subscription updated', ['tenant_id' => $tenantId, 'status' => $stripeSub->status]);
    }

    /**
     * Sync Tenant.subscription_status = 'canceled' when a subscription is deleted.
     */
    protected function handleSubscriptionDeleted(object $stripeSub): void
    {
        $tenantId = $this->resolveTenantIdFromSub($stripeSub);
        if (!$tenantId) {
            return;
        }

        Subscription::where('provider', 'stripe')
            ->where('provider_subscription_id', $stripeSub->id)
            ->update(['status' => 'canceled']);

        $tenant = Tenant::find($tenantId);
        $tenant?->syncSubscriptionStatus('canceled');

        Log::info('Subscription deleted/canceled', ['tenant_id' => $tenantId]);
    }

    private function resolveTenantIdFromSub(object $stripeSub): ?string
    {
        $sub = Subscription::where('provider', 'stripe')
            ->where('provider_subscription_id', $stripeSub->id)
            ->first();

        if ($sub) {
            return $sub->tenant_id;
        }

        // Fallback via BillingProfile customer ID
        $billingProfile = BillingProfile::where('provider', 'stripe')
            ->where('provider_customer_id', $stripeSub->customer ?? '')
            ->first();

        return $billingProfile?->tenant_id;
    }

    /**
     * 2️⃣ Chaque facture payée (1er paiement + renouvellements).
     *    Ici on crée systématiquement un Payment en DB centrale.
     */
    protected function handleInvoicePaymentSucceeded($invoice)
    {
        try {
            $subscriptionId = $invoice->subscription ?? null;
            $customerId     = $invoice->customer ?? null;

            if (! $subscriptionId) {
                Log::warning('invoice.payment_succeeded without subscription id');
                return;
            }

            Log::info('✅ handleInvoicePaymentSucceeded called', [
                'invoice_id'      => $invoice->id ?? null,
                'subscription_id' => $subscriptionId,
                'customer_id'     => $customerId,
            ]);

            // 1) Essayer de récupérer la subscription centrale
            $subscription = Subscription::where('provider', 'stripe')
                ->where('provider_subscription_id', $subscriptionId)
                ->first();

            $tenantId = $subscription->tenant_id ?? null;
            $planId   = $subscription->plan_id ?? null;

            // 2) Si pas de subscription centrale, essayer de retrouver tenant via BillingProfile
            if (! $tenantId && $customerId) {
                $billingProfile = BillingProfile::where('provider', 'stripe')
                    ->where('provider_customer_id', $customerId)
                    ->first();

                if ($billingProfile) {
                    $tenantId = $billingProfile->tenant_id;
                    Log::info('ℹ️ Tenant found via BillingProfile', [
                        'tenant_id' => $tenantId,
                    ]);
                }
            }

            // 3) Récupérer le plan via le price Stripe si besoin
            $priceId = null;
            if (! empty($invoice->lines->data[0]->price->id)) {
                $priceId = $invoice->lines->data[0]->price->id;
            }

            if (! $planId && $priceId) {
                $plan = Plan::where('stripe_price_id', $priceId)->first();
                if ($plan) {
                    $planId = $plan->id;
                    Log::info('ℹ️ Plan found via stripe_price_id', [
                        'plan_id'        => $planId,
                        'stripe_price_id'=> $priceId,
                    ]);
                }
            }

            if (! $tenantId) {
                Log::warning('❗ Could not resolve tenant_id for invoice', [
                    'invoice_id'      => $invoice->id ?? null,
                    'subscription_id' => $subscriptionId,
                    'customer_id'     => $customerId,
                ]);
                return;
            }

            // 4) Si la subscription centrale n’existe toujours pas, la créer rapidement
            if (! $subscription) {
                Log::info('ℹ️ Creating Subscription from invoice data', [
                    'tenant_id'        => $tenantId,
                    'subscription_id'  => $subscriptionId,
                    'plan_id'          => $planId,
                ]);

                // Récupérer la subscription Stripe pour plus de détails
                $stripeSub = StripeSubscription::retrieve($subscriptionId);

                $subscription = Subscription::updateOrCreate(
                    [
                        'tenant_id'                => $tenantId,
                        'provider'                 => 'stripe',
                        'provider_subscription_id' => $stripeSub->id,
                    ],
                    [
                        'plan_id'               => $planId,
                        'status'                => $stripeSub->status,
                        'trial_ends_at'         => $stripeSub->trial_end
                            ? Carbon::createFromTimestamp($stripeSub->trial_end)
                            : null,
                        'current_period_ends_at' => $stripeSub->current_period_end
                            ? Carbon::createFromTimestamp($stripeSub->current_period_end)
                            : null,
                    ]
                );
            }

            // 5) Période facturée
            $periodStart = null;
            $periodEnd   = null;

            if (!empty($invoice->lines->data[0]->period)) {
                $periodStart = Carbon::createFromTimestamp(
                    $invoice->lines->data[0]->period->start
                );
                $periodEnd = Carbon::createFromTimestamp(
                    $invoice->lines->data[0]->period->end
                );
            }

            // 6) Date de paiement
            $paidAt = null;
            if (!empty($invoice->status_transitions->paid_at)) {
                $paidAt = Carbon::createFromTimestamp(
                    $invoice->status_transitions->paid_at
                );
            } else {
                $paidAt = Carbon::createFromTimestamp($invoice->created);
            }

            // 7) Créer le Payment
            Payment::create([
                'tenant_id'           => $subscription->tenant_id,
                'subscription_id'     => $subscription->id,
                'provider'            => 'stripe',
                'provider_payment_id' => $invoice->id,
                'amount'              => $invoice->amount_paid / 100,
                'currency'            => strtoupper($invoice->currency),
                'status'              => $invoice->status ?? 'paid',
                'paid_at'             => $paidAt,
                'billing_period_start'=> $periodStart,
                'billing_period_end'  => $periodEnd,
            ]);

            Log::info('💶 Payment created from invoice.payment_succeeded', [
                'invoice_id'      => $invoice->id,
                'subscription_id' => $subscription->id,
                'tenant_id'       => $subscription->tenant_id,
            ]);

        } catch (\Throwable $e) {
            Log::error('💥 Error in handleInvoicePaymentSucceeded', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);
        }
    }
}