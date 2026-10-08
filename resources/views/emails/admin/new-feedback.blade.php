@php
    $row = 'padding:6px 0;color:#6b7280;width:150px;vertical-align:top;';
    $val = 'padding:6px 0;font-weight:600;word-break:break-word;';
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Nouveau retour utilisateur</title>
</head>
<body style="margin:0;padding:0;background:#f5f6fa;font-family:Arial,Helvetica,sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#f5f6fa;padding:32px 0;">
        <tr>
            <td align="center">
                <table width="560" cellpadding="0" cellspacing="0" role="presentation" style="background:#ffffff;border-radius:12px;overflow:hidden;max-width:560px;">
                    <tr>
                        <td style="background:#E91E63;padding:24px 32px;">
                            <span style="color:#ffffff;font-size:18px;font-weight:700;">💬 Nouveau retour utilisateur · {{ $feedback->typeLabel() }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 32px;color:#1a1a2e;font-size:14px;line-height:1.6;">
                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
                                <tr><td style="{{ $row }}">Type</td><td style="{{ $val }}">{{ $feedback->typeLabel() }}</td></tr>
                                <tr><td style="{{ $row }}">Source</td><td style="{{ $val }}">{{ $feedback->sourceLabel() }}</td></tr>
                                <tr><td style="{{ $row }}">Expéditeur</td><td style="{{ $val }}">{{ $feedback->user_name ? $feedback->user_name . ' · ' : '' }}{{ $feedback->email }}</td></tr>
                                @if ($feedback->tenant_id)
                                    <tr><td style="{{ $row }}">Entreprise</td><td style="{{ $val }}">{{ $feedback->company_name }} <span style="color:#9ca3af;font-weight:400;">({{ $feedback->tenant_id }})</span></td></tr>
                                @endif
                                @if ($feedback->page_url)
                                    <tr><td style="{{ $row }}">Page</td><td style="{{ $val }}">{{ $feedback->page_url }}</td></tr>
                                @endif
                                <tr><td style="{{ $row }}">Date</td><td style="{{ $val }}">{{ $feedback->created_at->format('d/m/Y H:i') }}</td></tr>
                                @if ($feedback->hasAttachment())
                                    <tr><td style="{{ $row }}">Capture d'écran</td><td style="{{ $val }}">Oui (visible dans le backoffice)</td></tr>
                                @endif
                            </table>

                            <div style="margin:20px 0 0;padding:16px 18px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;white-space:pre-line;color:#111827;">{{ $feedback->message }}</div>

                            <p style="margin:24px 0 0;">
                                <a href="{{ $adminUrl }}" style="display:inline-block;background:#E91E63;color:#ffffff;text-decoration:none;font-weight:700;padding:12px 24px;border-radius:8px;">Ouvrir dans le backoffice →</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
