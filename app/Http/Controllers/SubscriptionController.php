<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

use App\Models\Plan;
use App\Models\BillingProfile;
use App\Models\Subscription as AppSubscription;
use App\Models\Tenant;
use App\Models\Payment;
use Illuminate\Validation\Rule;
// Stripe
use Stripe\Stripe;
use Stripe\Customer as StripeCustomer;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Exception\InvalidRequestException;
use Stripe\Subscription as StripeSubscription;

class SubscriptionController extends Controller
{
    /**
     * Récupérer l'abonnement actuel du tenant (info centrale).
     */
    public function index(Request $request)
    {
        $tenantId = $this->resolveTenantId($request);

        if (! $tenantId) {
            return response()->json(['message' => 'Tenant introuvable'], 400);
        }

        $subscription = AppSubscription::with('plan')
            ->where('tenant_id', $tenantId)
            ->latest()
            ->first();

        if (! $subscription) {
            $trialEndsAt = $this->resolveTrialEndsAt($tenantId);

            if ($trialEndsAt) {
                return response()->json([
                    'subscription' => [
                        'status'                => 'trialing',
                        'trial_ends_at'         => $trialEndsAt,
                        'current_period_ends_at'=> $trialEndsAt,
                    ],
                    'trial' => [
                        'active'  => true,
                        'ends_at' => $trialEndsAt,
                    ],
                ]);
            }
        }

        if ($subscription?->plan) {
            $locale  = app()->getLocale();
            $sub     = $subscription->toArray();
            $rawName = $subscription->plan->getRawOriginal('name') ?? '';
            $nameArr = json_decode($rawName, true);
            $sub['plan']['name'] = is_array($nameArr)
                ? ($nameArr[$locale] ?? $nameArr['fr'] ?? $nameArr['en'] ?? $subscription->plan->slug ?? '')
                : $rawName;

            return response()->json(['subscription' => $sub]);
        }

        return response()->json([
            'subscription' => $subscription,
        ]);
    }

    /**
     * Crée une Stripe Checkout Session (page sécurisée Stripe)
     *
     * Request JSON:
     * - plan_id  (int)
     * - email    (string)
     *
     * Response JSON:
     * - checkout_url  (string) -> URL Stripe où rediriger l'utilisateur
     */
    public function createCheckoutSession(Request $request)
    {
        $tenantId = $this->resolveTenantId($request);

        if (! $tenantId) {
            return response()->json(['message' => 'Tenant introuvable'], 400);
        }


        $user_email = request()->user()->email;
        
        // Validation
        $validator = Validator::make($request->all(), [
            'plan_id' => [
                'required',
                'integer',
                // 👇 IMPORTANT : on force la connexion 'mysql' (centrale)
                Rule::exists('mysql.plans', 'id')->where('active', true),
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid data',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $plan = Plan::where('id', $request->plan_id)
            ->where('active', true)
            ->first();

        $priceId = $plan->stripe_price_id ?? null;

        if (! $priceId) {
            return response()->json([
                'message' => 'Ce plan n\'a pas d\'ID de prix Stripe configuré (stripe_price_id est vide).',
            ], 500);
        }

        Stripe::setApiKey(config('services.stripe.secret'));

        try {
            // 1) Profil de facturation central
            $billingProfile = BillingProfile::firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'provider'  => 'stripe',
                ],
                [
                    'email' => $user_email,
                ]
            );

            // 2) Créer / récupérer le customer Stripe
            if (! $billingProfile->provider_customer_id) {
                $stripeCustomer = StripeCustomer::create([
                    'email'    => $user_email,
                    'metadata' => [
                        'tenant_id' => $tenantId,
                    ],
                ]);

                $billingProfile->update([
                    'provider_customer_id' => $stripeCustomer->id,
                    'raw'                  => $stripeCustomer->toArray(),
                ]);
            } else {
                $stripeCustomer = StripeCustomer::retrieve($billingProfile->provider_customer_id);
            }

            // 2.bis) Si un abonnement Stripe est déjà actif, on le désactive avant de créer un nouveau plan
            $this->cancelExistingStripeSubscription($tenantId, $stripeCustomer->id);

            // 3) Créer la Checkout Session Stripe (mode: subscription)
            $successUrl = URL('/admin/subscription/checkout/success') . '?session_id={CHECKOUT_SESSION_ID}';
            $cancelUrl  = URL('/admin/subscription/checkout/cancel');

            $hasPreviousSubscription = AppSubscription::where('tenant_id', $tenantId)->exists();
            $trialEndsAt = null;

            if (! $hasPreviousSubscription) {
                $trialEndsAt = $this->resolveTrialEndsAt($tenantId);
            }

            $sessionPayload = [
                'mode'      => 'subscription',
                'customer'  => $stripeCustomer->id,
                'line_items' => [[
                    // 🔥 C'EST CETTE LIGNE QUI EST OBLIGATOIRE
                    'price'    => $priceId,   // ex: price_12345678
                    'quantity' => 1,
                ]],
                'success_url' => $successUrl,
                'cancel_url'  => $cancelUrl,
                'metadata' => [
                    'tenant_id' => tenant() ? tenant()->id : null,
                    'plan_id'   => $plan->id,
                ],
            ];

            if ($trialEndsAt) {
                $sessionPayload['subscription_data'] = [
                    'trial_end' => $trialEndsAt->getTimestamp(),
                ];
            }

            $session = StripeCheckoutSession::create($sessionPayload);

            // NOTE : on ne crée PAS la subscription en DB ici.
            // La vérité sera le webhook "checkout.session.completed" / "invoice.payment_succeeded".

            return response()->json([
                'checkout_url' => $session->url,
            ]);

        } catch (\Exception $e) {
            Log::error('Stripe Checkout session error', [
                'error'     => $e->getMessage(),
                'tenant_id' => $tenantId,
            ]);

            return response()->json([
                'message' => 'Erreur lors de la création de la session de paiement',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Désactive l'abonnement Stripe existant du tenant avant de souscrire à un nouveau plan.
     */
    protected function cancelExistingStripeSubscription(string $tenantId, ?string $stripeCustomerId = null): void
    {
        $existingList = AppSubscription::where('tenant_id', $tenantId)
            ->where('provider', 'stripe')
            ->whereNotIn('status', ['canceled'])
            ->latest()
            ->get();

        try {
            foreach ($existingList as $existing) {
                if (! $existing->provider_subscription_id) {
                    continue;
                }

                $stripeSub = StripeSubscription::retrieve($existing->provider_subscription_id);
                $canceledSub = $stripeSub->cancel();

                $periodEnd = $canceledSub->current_period_end ?? null;

                $existing->update([
                    'status'                 => $canceledSub->status,
                    'current_period_ends_at' => $periodEnd ? Carbon::createFromTimestamp($periodEnd) : null,
                    'raw'                    => $canceledSub,
                ]);
            }

            // Filet de sécurité : si des abonnements sont actifs côté Stripe pour ce customer, on les annule aussi
            if ($stripeCustomerId) {
                $activeStripeSubs = StripeSubscription::all([
                    'customer' => $stripeCustomerId,
                    'status'   => 'active',
                    'limit'    => 100,
                ]);

                foreach ($activeStripeSubs->data ?? [] as $stripeSub) {
                    $canceledSub = $stripeSub->cancel();
                    $periodEnd   = $canceledSub->current_period_end ?? null;

                    AppSubscription::where('provider_subscription_id', $stripeSub->id)
                        ->update([
                            'status'                 => $canceledSub->status,
                            'current_period_ends_at' => $periodEnd ? Carbon::createFromTimestamp($periodEnd) : null,
                            'raw'                    => $canceledSub,
                        ]);
                }
            }
        } catch (InvalidRequestException $e) {
            // Si la subscription Stripe n'existe plus côté Stripe, on marque l'ancienne comme annulée pour continuer.
            if (str_contains($e->getMessage(), 'No such subscription')) {
                foreach ($existingList as $existing) {
                    $existing->update([
                        'status'                 => 'canceled',
                        'current_period_ends_at' => null,
                        'raw'                    => ['error' => $e->getMessage()],
                    ]);
                }

                Log::warning('Stripe subscription already missing when canceling before new plan', [
                    'tenant_id' => $tenantId,
                    'subscription_ids' => $existingList->pluck('provider_subscription_id'),
                    'message' => $e->getMessage(),
                ]);

                return;
            }

            Log::error('Stripe cancel existing subscription before new plan', [
                'error'     => $e->getMessage(),
                'tenant_id' => $tenantId,
            ]);

            throw $e;
        } catch (\Exception $e) {
            Log::error('Stripe cancel existing subscription before new plan', [
                'error'     => $e->getMessage(),
                'tenant_id' => $tenantId,
            ]);

            // On relance l'erreur pour arrêter le checkout si l'annulation échoue
            throw $e;
        }
    }

    /**
     * Page de retour après succès Stripe Checkout.
     * (la vraie mise à jour de l'abonnement doit être faite via webhook)
     */
    public function checkoutSuccess(Request $request)
    {
        // Tu peux récupérer la session si tu veux afficher un message personnalisé
        $sessionId = $request->query('session_id');

        // Ici tu peux afficher une vue tenant :
        // return view('tenant.billing.success');
        return response()->json([
            'message'    => 'Payment en cours de traitement. Votre abonnement sera activé sous peu.',
            'session_id' => $sessionId,
        ]);
    }

    /**
     * Page de retour si l'utilisateur annule sur Stripe.
     */
    public function checkoutCancel(Request $request)
    {
        // Ici tu peux afficher une vue tenant :
        // return view('tenant.billing.cancel');
        return response()->json([
            'message' => 'Paiement annulé par l’utilisateur.',
        ]);
    }

    /**
     * Annuler l'abonnement (coté Stripe + DB)
     * (si tu veux, optionnel, ça passe par l'API Stripe Subscription)
     */
    public function cancel(Request $request)
    {
        $tenantId = $this->resolveTenantId($request);

        if (! $tenantId) {
            return response()->json(['message' => 'Tenant introuvable'], 400);
        }

        $subscription = AppSubscription::where('tenant_id', $tenantId)
            ->where('provider', 'stripe')
            ->whereNotIn('status', ['canceled'])
            ->latest()
            ->first();

        if (! $subscription) {
            return response()->json(['message' => 'Aucun abonnement actif à annuler'], 404);
        }

        Stripe::setApiKey(config('services.stripe.secret'));

        try {
            $stripeSub = \Stripe\Subscription::update($subscription->provider_subscription_id, [
                'cancel_at_period_end' => true,
            ]);

            $subscription->update([
                'status'                 => $stripeSub->status,
                'current_period_ends_at' => Carbon::createFromTimestamp($stripeSub->current_period_end),
                'raw'                    => $stripeSub,
            ]);

            return response()->json([
                'message'      => 'Abonnement annulé (à la fin de la période en cours).',
                'subscription' => $subscription,
            ]);
        } catch (\Exception $e) {
            Log::error('Stripe cancel subscription error', [
                'error'     => $e->getMessage(),
                'tenant_id' => $tenantId,
            ]);

            return response()->json([
                'message' => 'Erreur lors de l’annulation de l’abonnement',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Récupère l'ID du tenant courant via Stancl Tenancy.
     */
    protected function resolveTenantId(Request $request): ?string
    {
        // helper global fourni par stancl/tenancy
        if (function_exists('tenant') && tenant()) {
            // tu peux aussi faire tenant('id') si tu préfères
            return tenant()->id;
        }

        // fallback si jamais tu appelles depuis le central avec un tenant_id manuel
        return $request->input('tenant_id');
    }

    protected function resolveTrialEndsAt(?string $tenantId): ?Carbon
    {
        if (! $tenantId) {
            return null;
        }

        $tenant = Tenant::find($tenantId);

        if (! $tenant || ! $tenant->created_at) {
            return null;
        }

        $trialEndsAt = Carbon::parse($tenant->created_at)->addDays(30);

        return $trialEndsAt->isFuture() ? $trialEndsAt : null;
    }
}
