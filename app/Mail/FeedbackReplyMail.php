<?php

namespace App\Mail;

use App\Models\Feedback;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The administrator's answer to a Feedback entry, sent from the backoffice
 * (FeedbackService::reply()) in the sender's own language. Carries only the
 * reply and the sender's original message - never internal notes.
 */
class FeedbackReplyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Feedback $feedback,
        public readonly string $replyMessage,
    ) {
        $this->locale(in_array($feedback->locale, config('app.supported_locales', []), true) ? $feedback->locale : 'fr');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address(config('fakturalista.contact_email'), 'Fakturalista')],
            subject: __('emails.feedback_reply.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.feedback-reply');
    }
}
