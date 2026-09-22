<?php

namespace App\Mail;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Internal heads-up sent to config('fakturalista.admin_email') after a
 * self-service trial registration (routes/web.php POST /register) has
 * fully succeeded - see TenantProvisioningService::provision()'s
 * $selfService branch. Never sent for a failed/rolled-back registration,
 * since it is only triggered from that success path.
 *
 * Deliberately French-only (internal, not customer-facing) and carries no
 * secret: no password, hash, token or session data - see the view.
 */
class AdminNewRegistrationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly string $ownerName,
        public readonly string $ownerEmail,
        public readonly ?string $phone,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to:      [config('fakturalista.admin_email')],
            subject: 'Nouvelle inscription sur Fakturalista 🎉',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin.new-registration',
        );
    }
}
