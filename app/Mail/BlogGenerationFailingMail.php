<?php

namespace App\Mail;

use App\Models\BlogGeneration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Admin alert: the AI blog generation failed several times in a row (sent once per streak). */
class BlogGenerationFailingMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly BlogGeneration $generation, public readonly int $streak) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Blog IA : {$this->streak} échecs de génération consécutifs");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin.blog-generation-failing',
            with: ['adminUrl' => BlogArticleReadyMail::adminUrl($this->generation)],
        );
    }
}
