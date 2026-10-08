<?php

namespace App\Services\Feedback;

use App\Mail\FeedbackReplyMail;
use App\Mail\NewFeedbackAdminMail;
use App\Models\AdminUser;
use App\Models\Feedback;
use App\Models\FeedbackReply;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * The one writer of central Feedback rows. Attribution (source, tenant,
 * user, email, page host) is always derived here from server-side state -
 * the request only contributes type, message, visitor email, the page
 * path and an optional screenshot (see StoreFeedbackRequest).
 */
class FeedbackService
{
    public function submitFromPublicSite(array $data, ?UploadedFile $screenshot, Request $request): Feedback
    {
        return $this->create([
            'source'   => Feedback::SOURCE_PUBLIC,
            'type'     => $data['type'],
            'message'  => $data['message'],
            'email'    => Str::lower($data['email']),
            'page_url' => self::sanitizePageUrl($data['page_url'] ?? null, $request),
            'locale'   => app()->getLocale(),
        ] + $this->clientMeta($request), $screenshot);
    }

    public function submitFromTenant(array $data, ?UploadedFile $screenshot, Request $request, Tenant $tenant, User $user): Feedback
    {
        return $this->create([
            'source'       => Feedback::SOURCE_TENANT,
            'type'         => $data['type'],
            'message'      => $data['message'],
            'email'        => Str::lower($user->email),
            'tenant_id'    => $tenant->getTenantKey(),
            'user_id'      => $user->getKey(),
            'company_name' => $tenant->company_name ?: $tenant->getTenantKey(),
            'user_name'    => $user->name,
            'page_url'     => self::sanitizePageUrl($data['page_url'] ?? null, $request),
            'locale'       => $user->locale ?: app()->getLocale(),
        ] + $this->clientMeta($request), $screenshot);
    }

    /**
     * Emails the reply to the sender, then records it. Throws if sending
     * fails, so the history only ever lists replies that actually left.
     */
    public function reply(Feedback $feedback, string $message, ?AdminUser $admin): FeedbackReply
    {
        $message = trim($message);

        Mail::to($feedback->email)->send(new FeedbackReplyMail($feedback, $message));

        $reply = $feedback->replies()->create([
            'admin_user_id' => $admin?->getKey(),
            'message'       => $message,
            'sent_at'       => now(),
        ]);

        if ($feedback->status === 'new') {
            $feedback->update(['status' => 'in_progress']);
        }

        return $reply;
    }

    /**
     * Keeps only the path of the page the widget was opened on, on the host
     * that actually served this request: query strings and fragments can
     * carry tokens (password reset, signup ticket), and the host is never
     * taken from the client.
     */
    public static function sanitizePageUrl(?string $url, Request $request): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '' || ! str_starts_with($path, '/')) {
            $path = '/';
        }

        $path = preg_replace('/[^\x21-\x7E]/', '', $path) ?: '/';

        return Str::limit($request->getSchemeAndHttpHost() . $path, 2000, '');
    }

    private function create(array $attributes, ?UploadedFile $screenshot): Feedback
    {
        if ($screenshot) {
            $attributes += $this->storeScreenshot($screenshot);
        }

        try {
            $feedback = Feedback::create($attributes);
        } catch (Throwable $e) {
            if (isset($attributes['attachment_path'])) {
                Storage::disk(Feedback::DISK)->delete($attributes['attachment_path']);
            }
            throw $e;
        }

        try {
            Mail::send(new NewFeedbackAdminMail($feedback));
        } catch (Throwable $e) {
            // The feedback is saved and visible in the backoffice either way.
            report($e);
        }

        return $feedback;
    }

    /** Random, content-derived name in a private central disk. */
    private function storeScreenshot(UploadedFile $file): array
    {
        $extension = match ($file->getMimeType()) {
            'image/png'  => 'png',
            'image/webp' => 'webp',
            default      => 'jpg',
        };

        $path = $file->storeAs(now()->format('Y/m'), Str::uuid() . '.' . $extension, Feedback::DISK);

        $originalName = Str::limit(
            preg_replace('/[^\w.\- ]/u', '_', $file->getClientOriginalName()) ?: 'capture.' . $extension,
            200,
            ''
        );

        return [
            'attachment_path' => $path,
            'attachment_name' => $originalName,
            'attachment_mime' => $file->getMimeType(),
            'attachment_size' => $file->getSize(),
        ];
    }

    private function clientMeta(Request $request): array
    {
        return [
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
        ];
    }
}
