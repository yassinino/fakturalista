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
 * Settings > Notifications - "Invoice paid" (covers both "payment
 * received" and "invoice paid": in this codebase they're the same event -
 * an issued invoice's status becoming Invoice::STATUS_PAID, however it got
 * there (manual record, Stripe checkout, Stripe webhook) - see
 * App\Observers\InvoiceObserver, the single place this is dispatched from.
 */
class InvoicePaidNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly Invoice $invoice,
        public readonly string $invoiceUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to:      [$this->tenant->owner_email],
            subject: __('emails.invoice_paid_notification.subject', ['reference' => $this->invoice->reference]),
        );
    }

    public function content(): Content
    {
        $currency = app(TenantContextService::class)->currency();

        return new Content(
            view: 'emails.tenant.invoice-paid-notification',
            with: [
                'formattedTotal' => app(CurrencyFormatter::class)->format((float) $this->invoice->total, $currency, app()->getLocale()),
                'customerName'   => $this->invoice->customer?->name ?? $this->invoice->customer?->company_name ?? '',
            ],
        );
    }
}
