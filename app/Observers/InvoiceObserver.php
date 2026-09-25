<?php

namespace App\Observers;

use App\Mail\InvoicePaidNotificationMail;
use App\Models\CompanyProfile;
use App\Models\Invoice;
use App\Services\NotificationPreferencesService;
use App\Services\TenantContextService;
use Illuminate\Support\Facades\Mail;

/**
 * Settings > Notifications - "Invoice paid". Fires for every path that can
 * mark an invoice paid (InvoicePaymentsController::record(), the Stripe
 * checkout/webhook handlers, InvoiceController) without touching any of
 * them - they all set `status` via an Eloquent instance save/update, never
 * a query-builder mass update, so this single hook sees every one.
 */
class InvoiceObserver
{
    public function updated(Invoice $invoice): void
    {
        if (!$invoice->wasChanged('status') || $invoice->status !== Invoice::STATUS_PAID) {
            return;
        }

        $tenant = tenancy()->tenant;

        if (!$tenant || empty($tenant->owner_email)) {
            return;
        }

        $context = app(TenantContextService::class);
        $profile = CompanyProfile::first();

        if (!app(NotificationPreferencesService::class)->isEnabled($profile?->notification_preferences, 'invoice_paid')) {
            return;
        }

        $invoice->loadMissing('customer');

        $domain = $tenant->domains->first()?->domain;
        $invoiceUrl = ($domain ? 'https://' . $domain : url('/')) . '/admin/invoices/edit/' . $invoice->uuid;

        Mail::send(
            (new InvoicePaidNotificationMail($tenant, $invoice, $invoiceUrl))
                ->locale($context->locale())
        );
    }
}
