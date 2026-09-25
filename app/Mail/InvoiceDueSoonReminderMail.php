<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\CurrencyFormatter;
use App\Services\TenantContextService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Settings > Notifications - "Invoice due soon", one of the configurable
 * before-due-date reminder days (1/3/7 - see
 * NotificationPreferencesService::DUE_SOON_DAYS). Sent to the tenant
 * owner, never the customer - see App\Console\Commands\SendInvoiceRemindersCommand,
 * the only place this is dispatched from.
 */
class InvoiceDueSoonReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly Invoice $invoice,
        public readonly int $daysUntilDue,
        public readonly string $invoiceUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to:      [$this->tenant->owner_email],
            subject: __('emails.invoice_due_reminder.subject', ['reference' => $this->invoice->reference]),
        );
    }

    public function content(): Content
    {
        $currency = app(TenantContextService::class)->currency();

        return new Content(
            view: 'emails.tenant.invoice-due-reminder',
            with: [
                'formattedTotal' => app(CurrencyFormatter::class)->format((float) $this->invoice->total, $currency, app()->getLocale()),
                'customerName'   => $this->invoice->customer?->name ?? $this->invoice->customer?->company_name ?? '',
            ],
        );
    }
}
