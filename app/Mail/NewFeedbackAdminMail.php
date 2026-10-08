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
 * Internal heads-up to config('fakturalista.admin_email') for every new
 * Feedback entry (FeedbackService). French-only like AdminNewRegistrationMail;
 * Reply-To is the sender so a quick answer from the mailbox also works.
 */
class NewFeedbackAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Feedback $feedback) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to:      [config('fakturalista.admin_email')],
            replyTo: [new Address($this->feedback->email)],
            subject: sprintf('Nouveau retour (%s) #%d', $this->feedback->typeLabel(), $this->feedback->id),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin.new-feedback',
            with: ['adminUrl' => self::adminUrl($this->feedback)],
        );
    }

    /**
     * Backoffice detail page on the central domain - built from APP_URL,
     * not the current request, since tenant-app submissions run on a
     * tenant subdomain.
     */
    public static function adminUrl(Feedback $feedback): string
    {
        return rtrim((string) config('app.url'), '/')
            . route('filament.backoffice.resources.feedback.view', ['record' => $feedback], false);
    }
}
