@php($rtl = in_array(app()->getLocale(), config('app.rtl_locales', []), true))
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('emails.feedback_reply.subject') }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f5f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#f4f5f7;padding:40px 12px;">
<tr><td align="center">
    <table width="580" cellpadding="0" cellspacing="0" role="presentation" style="max-width:580px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;text-align:{{ $rtl ? 'right' : 'left' }};">
        <tr>
            <td style="background:#1a1a2e;padding:28px 40px;text-align:center;">
                <span style="color:#fa7070;font-size:22px;font-weight:700;letter-spacing:-0.5px;">Faktura<span style="color:#ffffff;">lista</span></span>
            </td>
        </tr>
        <tr>
            <td style="padding:36px 40px 8px;color:#374151;font-size:15px;line-height:1.7;">
                <p style="margin:0 0 18px;">{{ $feedback->user_name ? __('emails.feedback_reply.greeting', ['name' => $feedback->user_name]) : __('emails.feedback_reply.greeting_plain') }}</p>
                <p style="margin:0 0 18px;">{{ __('emails.feedback_reply.intro') }}</p>
                <div style="white-space:pre-line;color:#111827;margin:0 0 24px;">{{ $replyMessage }}</div>
                <p style="margin:0 0 6px;">{{ __('emails.feedback_reply.signature') }}</p>
            </td>
        </tr>
        <tr>
            <td style="padding:16px 40px 32px;">
                <div style="border-{{ $rtl ? 'right' : 'left' }}:3px solid #e5e7eb;padding:4px 16px;color:#6b7280;font-size:13px;line-height:1.6;">
                    <div style="font-weight:600;margin-bottom:4px;">{{ __('emails.feedback_reply.original_label', ['date' => $feedback->created_at->format('d/m/Y')]) }}</div>
                    <div style="white-space:pre-line;">{{ \Illuminate\Support\Str::limit($feedback->message, 1200) }}</div>
                </div>
            </td>
        </tr>
        <tr>
            <td style="background:#f8fafc;border-top:1px solid #e5e7eb;padding:20px 40px;text-align:center;font-size:12px;color:#9ca3af;">
                <span style="font-weight:700;color:#6b7280;"><span style="color:#fa7070;">Faktura</span>lista</span>
                &middot; {{ __('emails.feedback_reply.footer_note') }}
            </td>
        </tr>
    </table>
</td></tr>
</table>
</body>
</html>
