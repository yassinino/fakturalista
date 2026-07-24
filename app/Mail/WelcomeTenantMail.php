<?php

namespace App\Mail;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeTenantMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param string $plainPassword  Plain-text password for the welcome email only.
     *                               It is never stored in the database - the user
     *                               record is always created with Hash::make().
     *                               With QUEUE_CONNECTION=sync this never touches a
     *                               queue store. If you switch to database/redis queues,
     *                               consider sending the email synchronously instead.
     */
    public function __construct(
        public readonly Tenant $tenant,
        public readonly string $adminEmail,
        public readonly string $adminName,
        public readonly string $plainPassword,
        public readonly string $loginUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to:      [$this->adminEmail],
            subject: '¡Bienvenido a Fakturalista! Tu cuenta está lista',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tenant.welcome',
        );
    }
}
