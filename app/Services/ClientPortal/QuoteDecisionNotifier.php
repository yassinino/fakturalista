<?php

namespace App\Services\ClientPortal;

use App\Mail\QuoteDecisionNotificationMail;
use App\Models\CompanyProfile;
use App\Models\Quote;
use App\Services\NotificationPreferencesService;
use App\Services\TenantContextService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Settings > Notifications - "Quote accepted or rejected" (Client Portal
 * Step 5). Called explicitly by ClientPortalController after a successful
 * accept/reject rather than from App\Observers\QuoteObserver: the portal
 * transition is a single conditional UPDATE (race-safe, see
 * ClientPortalController::transitionQuote()), which fires no Eloquent
 * model events. Recipient, preference check and URL building follow
 * QuoteObserver's "Quote converted" notification exactly.
 */
class QuoteDecisionNotifier
{
    public function __construct(
        private NotificationPreferencesService $preferences,
        private TenantContextService $tenantContext,
    ) {
    }

    public function notify(Quote $quote): void
    {
        if (!in_array($quote->status, [Quote::STATUS_ACCEPTED, Quote::STATUS_REJECTED], true)) {
            return;
        }

        $tenant = tenancy()->tenant;

        if (!$tenant || empty($tenant->owner_email)) {
            return;
        }

        $profile = CompanyProfile::first();

        if (!$this->preferences->isEnabled($profile?->notification_preferences, 'quote_decision')) {
            return;
        }

        $quote->loadMissing('customer');

        $domain   = $tenant->domains->first()?->domain;
        $quoteUrl = ($domain ? 'https://' . $domain : url('/')) . '/admin/quotes/edit/' . $quote->uuid;

        // The customer's decision is already recorded - a mail failure
        // must never turn their successful accept/reject into an error.
        try {
            Mail::send(
                (new QuoteDecisionNotificationMail($tenant, $quote, $quote->status, $quoteUrl))
                    ->locale($this->tenantContext->locale())
            );
        } catch (\Throwable $e) {
            Log::error('Client Portal quote decision notification failed', [
                'quote_uuid' => $quote->uuid,
                'exception'  => $e->getMessage(),
            ]);
        }
    }
}
