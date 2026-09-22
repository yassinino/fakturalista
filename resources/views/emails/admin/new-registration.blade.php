<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Nouvelle inscription sur Fakturalista</title>
</head>
<body style="margin:0;padding:0;background:#f5f6fa;font-family:Arial,Helvetica,sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#f5f6fa;padding:32px 0;">
        <tr>
            <td align="center">
                <table width="480" cellpadding="0" cellspacing="0" role="presentation" style="background:#ffffff;border-radius:12px;overflow:hidden;max-width:480px;">
                    <tr>
                        <td style="background:#E91E63;padding:24px 32px;">
                            <span style="color:#ffffff;font-size:18px;font-weight:700;">🎉 Nouvelle inscription sur Fakturalista</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 32px;color:#1a1a2e;font-size:14px;line-height:1.6;">
                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
                                <tr>
                                    <td style="padding:6px 0;color:#6b7280;width:170px;vertical-align:top;">Nom</td>
                                    <td style="padding:6px 0;font-weight:600;">{{ $ownerName }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:6px 0;color:#6b7280;vertical-align:top;">E-mail</td>
                                    <td style="padding:6px 0;font-weight:600;">{{ $ownerEmail }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:6px 0;color:#6b7280;vertical-align:top;">Téléphone</td>
                                    <td style="padding:6px 0;font-weight:600;">{{ $phone ?: 'Non renseigné' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:6px 0;color:#6b7280;vertical-align:top;">Entreprise / workspace</td>
                                    <td style="padding:6px 0;font-weight:600;">{{ $tenant->company_name }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:6px 0;color:#6b7280;vertical-align:top;">Pays</td>
                                    <td style="padding:6px 0;font-weight:600;">{{ $tenant->country === 'MA' ? 'Maroc' : ($tenant->country ?? 'Maroc') }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:6px 0;color:#6b7280;vertical-align:top;">Date d'inscription</td>
                                    <td style="padding:6px 0;font-weight:600;">{{ $tenant->created_at->format('d/m/Y H:i') }}</td>
                                </tr>
                            </table>
                            <p style="margin:24px 0 0;color:#374151;">Un nouvel utilisateur a démarré l'essai gratuit de Fakturalista.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
