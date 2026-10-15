<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Échecs de génération du blog</title>
</head>
<body style="margin:0;padding:0;background:#f5f6fa;font-family:Arial,Helvetica,sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#f5f6fa;padding:32px 0;">
        <tr>
            <td align="center">
                <table width="560" cellpadding="0" cellspacing="0" role="presentation" style="background:#ffffff;border-radius:12px;overflow:hidden;max-width:560px;">
                    <tr>
                        <td style="background:#b91c1c;padding:24px 32px;">
                            <span style="color:#ffffff;font-size:18px;font-weight:700;">⚠️ Blog IA : {{ $streak }} échecs consécutifs</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 32px;color:#1a1a2e;font-size:14px;line-height:1.6;">
                            <p style="margin:0 0 12px;">La génération automatique d'articles a échoué {{ $streak }} fois de suite. Le site n'est pas affecté ; aucun article n'a été publié.</p>
                            <p style="margin:0 0 6px;font-weight:700;">Dernière erreur (génération #{{ $generation->id }}, {{ $generation->run_date?->format('d/m/Y') }}) :</p>
                            <div style="padding:14px 16px;background:#fef2f2;border:1px solid #fecaca;border-radius:10px;color:#7f1d1d;">{{ $generation->error_message }}</div>
                            <p style="margin:16px 0 0;color:#6b7280;">Causes fréquentes : clé GEMINI_API_KEY absente ou invalide, quota Gemini atteint, modèle indisponible.</p>
                            <p style="margin:24px 0 0;">
                                <a href="{{ $adminUrl }}" style="display:inline-block;background:#E91E63;color:#ffffff;text-decoration:none;font-weight:700;padding:12px 24px;border-radius:8px;">Voir et relancer →</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
