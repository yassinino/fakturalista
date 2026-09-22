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
 * Welcome email for a SELF-SERVICE trial signup (routes/web.php POST /register
 * -> RegisterTrialController -> TenantProvisioningService::provision(..., selfService: true)).
 *
 * Deliberately separate from WelcomeTenantMail (the admin-created-tenant
 * email): that one exists specifically to hand a Filament-admin-generated
 * password to a user who never typed one themselves. Here, the user chose
 * their own password during registration - it is never captured by this
 * mailable, never appears in this template, and is never logged.
 */
class WelcomeSelfServiceMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly string $ownerEmail,
        public readonly string $ownerName,
        public readonly string $loginUrl,
    ) {}

    public function envelope(): Envelope
    {
        // Renders in whatever locale ->locale() was called with (set by
        // TenantProvisioningService before sending) - Laravel's Mailer
        // sets the translator locale before building the envelope/content,
        // and restores it after, for both synchronous and truly-queued
        // sends (the locale travels with the queued job).
        return new Envelope(
            to:      [$this->ownerEmail],
            subject: __('emails.welcome_self_service.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tenant.welcome-self-service',
        );
    }
}
