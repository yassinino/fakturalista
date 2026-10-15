<?php

namespace App\Mail;

use App\Models\BlogGeneration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Admin heads-up for a new AI blog article: published automatically, or
 * saved as a draft that needs a review (with the reasons). Sent once per
 * generation (BlogAutomationService). French-only like the other admin mails.
 */
class BlogArticleReadyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly BlogGeneration $generation) {}

    public function envelope(): Envelope
    {
        $published = $this->generation->status === BlogGeneration::STATUS_PUBLISHED;

        return new Envelope(
            subject: ($published ? 'Article de blog publié : ' : 'Article de blog à relire : ') . $this->generation->article_title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin.blog-article-ready',
            with: ['adminUrl' => self::adminUrl($this->generation)],
        );
    }

    /** Backoffice page of the generation, on the central domain (APP_URL). */
    public static function adminUrl(BlogGeneration $generation): string
    {
        return rtrim((string) config('app.url'), '/')
            . route('filament.backoffice.resources.blog-generations.view', ['record' => $generation], false);
    }
}
