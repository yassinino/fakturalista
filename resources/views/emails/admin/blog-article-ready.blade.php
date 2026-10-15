@php
    $published = $generation->status === \App\Models\BlogGeneration::STATUS_PUBLISHED;
    $row = 'padding:6px 0;color:#6b7280;width:150px;vertical-align:top;';
    $val = 'padding:6px 0;font-weight:600;word-break:break-word;';
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $published ? 'Article publié' : 'Article à relire' }}</title>
</head>
<body style="margin:0;padding:0;background:#f5f6fa;font-family:Arial,Helvetica,sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#f5f6fa;padding:32px 0;">
        <tr>
            <td align="center">
                <table width="560" cellpadding="0" cellspacing="0" role="presentation" style="background:#ffffff;border-radius:12px;overflow:hidden;max-width:560px;">
                    <tr>
                        <td style="background:#E91E63;padding:24px 32px;">
                            <span style="color:#ffffff;font-size:18px;font-weight:700;">📝 {{ $published ? 'Nouvel article publié sur le blog' : 'Nouvel article à relire' }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 32px;color:#1a1a2e;font-size:14px;line-height:1.6;">
                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
                                <tr><td style="{{ $row }}">Titre</td><td style="{{ $val }}">{{ $generation->article_title }}</td></tr>
                                <tr><td style="{{ $row }}">Marché</td><td style="{{ $val }}">{{ $generation->countryLabel() }}</td></tr>
                                <tr><td style="{{ $row }}">Mot-clé</td><td style="{{ $val }}">{{ $generation->primary_keyword }}</td></tr>
                                <tr><td style="{{ $row }}">Mots</td><td style="{{ $val }}">{{ $generation->word_count }}</td></tr>
                                <tr><td style="{{ $row }}">Réglementaire</td><td style="{{ $val }}">{{ $generation->regulatory ? ($generation->sources_verified ? 'Oui, sources officielles citées' : 'Oui, sources NON vérifiées') : 'Non' }}</td></tr>
                            </table>

                            @if (! $published && $generation->draft_reasons)
                                <p style="margin:20px 0 6px;font-weight:700;">Pourquoi il n'a pas été publié :</p>
                                <ul style="margin:0;padding-left:20px;">
                                    @foreach ($generation->draft_reasons as $reason)
                                        <li>{{ $reason }}</li>
                                    @endforeach
                                </ul>
                            @endif

                            @if ($generation->validation_errors)
                                <p style="margin:20px 0 6px;font-weight:700;color:#b91c1c;">Contrôle qualité :</p>
                                <ul style="margin:0;padding-left:20px;color:#b91c1c;">
                                    @foreach (array_slice($generation->validation_errors, 0, 8) as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            @endif

                            <p style="margin:24px 0 0;">
                                <a href="{{ $adminUrl }}" style="display:inline-block;background:#E91E63;color:#ffffff;text-decoration:none;font-weight:700;padding:12px 24px;border-radius:8px;">{{ $published ? 'Voir dans le backoffice →' : 'Relire et publier →' }}</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
