<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\Quote;
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
 * Settings > Notifications - "Quote converted" - dispatched from
 * App\Observers\QuoteObserver when a quote's status becomes
 * Quote::STATUS_CONVERTED (currently only possible via QuoteController::convert()).
 */
class QuoteConvertedNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly Quote $quote,
        public readonly ?Invoice $invoice,
        public readonly string $invoiceUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to:      [$this->tenant->owner_email],
            subject: __('emails.quote_converted_notification.subject', ['reference' => $this->quote->reference]),
        );
    }

    public function content(): Content
    {
        $currency = app(TenantContextService::class)->currency();

        return new Content(
            view: 'emails.tenant.quote-converted-notification',
            with: [
                'formattedTotal' => app(CurrencyFormatter::class)->format((float) $this->quote->total, $currency, app()->getLocale()),
                'customerName'   => $this->quote->customer?->name ?? $this->quote->customer?->company_name ?? '',
            ],
        );
    }
}
