<?php

namespace App\Observers;

use App\Mail\QuoteConvertedNotificationMail;
use App\Models\CompanyProfile;
use App\Models\Invoice;
use App\Models\Quote;
use App\Services\NotificationPreferencesService;
use App\Services\TenantContextService;
use Illuminate\Support\Facades\Mail;

/**
 * Settings > Notifications - "Quote converted". Fires for the only path
 * that can convert a quote today (QuoteController::convert() via
 * QuoteToInvoiceService, an Eloquent instance save) without touching
 * either of them.
 */
class QuoteObserver
{
    public function updated(Quote $quote): void
    {
        if (!$quote->wasChanged('status') || $quote->status !== Quote::STATUS_CONVERTED) {
            return;
        }

        $tenant = tenancy()->tenant;

        if (!$tenant || empty($tenant->owner_email)) {
            return;
        }

        $context = app(TenantContextService::class);
        $profile = CompanyProfile::first();

        if (!app(NotificationPreferencesService::class)->isEnabled($profile?->notification_preferences, 'quote_converted')) {
            return;
        }

        $quote->loadMissing('customer');
        $invoice = Invoice::find($quote->invoice_id);

        $domain = $tenant->domains->first()?->domain;
        $invoiceUrl = $invoice
            ? ($domain ? 'https://' . $domain : url('/')) . '/admin/invoices/edit/' . $invoice->uuid
            : ($domain ? 'https://' . $domain : url('/')) . '/admin/quotes';

        Mail::send(
            (new QuoteConvertedNotificationMail($tenant, $quote, $invoice, $invoiceUrl))
                ->locale($context->locale())
        );
    }
}
