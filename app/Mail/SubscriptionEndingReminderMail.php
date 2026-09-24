<?php

namespace App\Mail;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionEndingReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param int $daysLeft 3 or 1 - which of the two reminder variants to render.
     */
    public function __construct(
        public readonly Tenant $tenant,
        public readonly int $daysLeft,
        public readonly string $subscribeUrl,
    ) {}

    public function envelope(): Envelope
    {
        // Renders in whatever locale ->locale() was called with (set by
        // SendBillingRemindersCommand before sending, per tenant).
        return new Envelope(
            to:      [$this->tenant->owner_email],
            subject: $this->daysLeft === 1
                ? __('emails.subscription_reminder.subject_1_day')
                : __('emails.subscription_reminder.subject_3_days'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tenant.subscription-ending-reminder',
        );
    }
}
