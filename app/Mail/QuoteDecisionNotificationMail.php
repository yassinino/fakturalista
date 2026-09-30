<?php

namespace App\Mail;

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
 * Settings > Notifications - "Quote accepted or rejected" - dispatched
 * from App\Services\ClientPortal\QuoteDecisionNotifier when a customer
 * accepts/rejects a quote from the Client Portal (Step 5). Same shape
 * as QuoteConvertedNotificationMail.
 */
class QuoteDecisionNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** @param string $decision Quote::STATUS_ACCEPTED or Quote::STATUS_REJECTED */
    public function __construct(
        public readonly Tenant $tenant,
        public readonly Quote $quote,
        public readonly string $decision,
        public readonly string $quoteUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to:      [$this->tenant->owner_email],
            subject: __('emails.quote_decision_notification.subject_' . $this->decision, [
                'reference' => $this->quote->reference,
                'client'    => $this->customerName(),
            ]),
        );
    }

    public function content(): Content
    {
        $context   = app(TenantContextService::class);
        $decidedAt = $this->decision === Quote::STATUS_ACCEPTED ? $this->quote->accepted_at : $this->quote->rejected_at;

        return new Content(
            view: 'emails.tenant.quote-decision-notification',
            with: [
                'formattedTotal' => app(CurrencyFormatter::class)->format((float) $this->quote->total, $context->currency(), app()->getLocale()),
                'customerName'   => $this->customerName(),
                'decidedAt'      => $decidedAt
                    ? $decidedAt->copy()->setTimezone($context->timezone())->locale(app()->getLocale())->isoFormat('LLL')
                    : '',
            ],
        );
    }

    private function customerName(): string
    {
        return $this->quote->customer?->name ?? $this->quote->customer?->company_name ?? '';
    }
}
