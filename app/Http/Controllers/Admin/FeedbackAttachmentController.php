<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a feedback screenshot from the private 'feedback' disk. Registered
 * as a Filament authenticated route (AdminPanelProvider), so only a
 * logged-in backoffice admin ('admin' guard) ever reaches it.
 */
class FeedbackAttachmentController extends Controller
{
    public function __invoke(Feedback $feedback): StreamedResponse
    {
        abort_unless(auth('admin')->check(), 403);
        abort_unless($feedback->hasAttachment() && Storage::disk(Feedback::DISK)->exists($feedback->attachment_path), 404);

        return Storage::disk(Feedback::DISK)->response(
            $feedback->attachment_path,
            $feedback->attachment_name,
            [
                'Content-Type'            => $feedback->attachment_mime,
                'X-Content-Type-Options'  => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; sandbox",
                'Cache-Control'           => 'private, no-store',
            ],
            'inline'
        );
    }
}
