<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User   $user,
        public readonly string $resetUrl,
    ) {}

    public function build(): static
    {
        return $this
            ->subject('Reset your Fakturalista password')
            ->view('emails.password_reset');
    }
}
