<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Bienvenido a Fakturalista</title>
    <!--[if mso]>
    <noscript>
        <xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml>
    </noscript>
    <![endif]-->
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body, table, td, p, a, li { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-collapse: collapse; }
        img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; }

        body {
            background-color: #f4f5f7;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            margin: 0;
            padding: 0;
            width: 100% !important;
            min-width: 100%;
        }

        .email-wrapper  { background-color: #f4f5f7; padding: 40px 20px; width: 100%; }
        .email-card     { background: #ffffff; border-radius: 12px; max-width: 580px; margin: 0 auto; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }

        .header         { background: #1a1a2e; padding: 32px 40px; text-align: center; }
        .header-logo    { color: #fa7070; font-size: 22px; font-weight: 700; letter-spacing: -0.5px; text-decoration: none; }
        .header-logo span { color: #ffffff; }

        .hero           { background: linear-gradient(135deg, #fa7070 0%, #e05555 100%); padding: 40px 40px 36px; text-align: center; }
        .hero-title     { color: #ffffff; font-size: 26px; font-weight: 700; letter-spacing: -0.4px; line-height: 1.25; margin-bottom: 10px; }
        .hero-subtitle  { color: rgba(255,255,255,0.85); font-size: 15px; line-height: 1.6; }

        .body           { padding: 36px 40px; }
        .greeting       { font-size: 16px; color: #374151; line-height: 1.6; margin-bottom: 24px; }
        .greeting strong { color: #111827; }

        .info-block     { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 10px; padding: 24px 28px; margin-bottom: 28px; }
        .info-label     { color: #6b7280; font-size: 12px; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; min-width: 140px; padding-top: 1px; }
        .info-value     { color: #111827; font-size: 14px; font-weight: 500; word-break: break-all; }
        .info-value a   { color: #fa7070; text-decoration: none; }

        .cta-wrapper    { text-align: center; margin: 28px 0 32px; }
        .cta-button     {
            display: inline-block;
            background: #fa7070;
            color: #ffffff !important;
            text-decoration: none !important;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 0.02em;
            padding: 14px 36px;
            border-radius: 10px;
        }

        .trial-note     {
            background: #f0fdf4;
            border-left: 4px solid #22c55e;
            border-radius: 0 8px 8px 0;
            padding: 14px 18px;
            margin-bottom: 28px;
            font-size: 13px;
            color: #166534;
            line-height: 1.6;
        }
        .trial-note strong { color: #14532d; }

        .divider        { border: none; border-top: 1px solid #e5e7eb; margin: 28px 0; }

        .help-text      { font-size: 14px; color: #6b7280; line-height: 1.7; margin-bottom: 8px; }
        .help-text a    { color: #fa7070; text-decoration: none; font-weight: 500; }

        .footer         { background: #f8fafc; border-top: 1px solid #e5e7eb; padding: 24px 40px; text-align: center; }
        .footer-brand   { font-size: 13px; font-weight: 700; color: #6b7280; margin-bottom: 8px; }
        .footer-brand span { color: #fa7070; }
        .footer-links   { font-size: 12px; color: #9ca3af; }
        .footer-links a { color: #9ca3af; text-decoration: underline; }

        @media only screen and (max-width: 600px) {
            .email-wrapper  { padding: 20px 12px !important; }
            .header         { padding: 24px 24px !important; }
            .hero           { padding: 32px 24px 28px !important; }
            .body           { padding: 28px 24px !important; }
            .footer         { padding: 20px 24px !important; }
            .hero-title     { font-size: 22px !important; }
            .cta-button     { display: block !important; text-align: center !important; }
        }
    </style>
</head>
<body>
<div class="email-wrapper">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr><td align="center">
<div class="email-card">

    <div class="header">
        <div class="header-logo">Faktura<span>lista</span></div>
    </div>

    <div class="hero">
        <table width="56" cellpadding="0" cellspacing="0" style="margin:0 auto 20px;" role="presentation">
            <tr><td width="56" height="56" bgcolor="#ffffff33" style="border-radius:50%;text-align:center;vertical-align:middle;">
                <span style="font-size:26px;line-height:56px;display:block;">🎉</span>
            </td></tr>
        </table>
        <div class="hero-title">¡Bienvenido a Fakturalista,<br>{{ $ownerName }}!</div>
        <div class="hero-subtitle">Tu cuenta ya está lista para usarse.</div>
    </div>

    <div class="body">

        <p class="greeting">
            Hola <strong>{{ $ownerName }}</strong>,<br><br>
            Tu cuenta de Fakturalista ya está lista. Tu espacio de trabajo para
            <strong>{{ $tenant->company_name }}</strong> ha sido creado correctamente.
        </p>

        <div class="info-block">
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
                <tr>
                    <td class="info-label" style="padding:8px 0;color:#6b7280;font-size:12px;font-weight:600;letter-spacing:0.05em;text-transform:uppercase;width:140px;vertical-align:top;">
                        Empresa
                    </td>
                    <td class="info-value" style="padding:8px 0;color:#111827;font-size:14px;font-weight:500;">
                        {{ $tenant->company_name }}
                    </td>
                </tr>
                <tr><td colspan="2" style="border-top:1px solid #f1f3f5;"></td></tr>
                <tr>
                    <td class="info-label" style="padding:8px 0;color:#6b7280;font-size:12px;font-weight:600;letter-spacing:0.05em;text-transform:uppercase;width:140px;vertical-align:top;">
                        URL de acceso
                    </td>
                    <td class="info-value" style="padding:8px 0;color:#111827;font-size:14px;font-weight:500;word-break:break-all;">
                        <a href="{{ $loginUrl }}" style="color:#fa7070;text-decoration:none;">{{ $loginUrl }}</a>
                    </td>
                </tr>
                <tr><td colspan="2" style="border-top:1px solid #f1f3f5;"></td></tr>
                <tr>
                    <td class="info-label" style="padding:8px 0;color:#6b7280;font-size:12px;font-weight:600;letter-spacing:0.05em;text-transform:uppercase;width:140px;vertical-align:top;">
                        Email
                    </td>
                    <td class="info-value" style="padding:8px 0;color:#111827;font-size:14px;font-weight:500;word-break:break-all;">
                        {{ $ownerEmail }}
                    </td>
                </tr>
            </table>
        </div>

        <div class="trial-note">
            <strong>🎁 14 días gratis, sin tarjeta de crédito.</strong>
            Tienes dos semanas para probar Fakturalista sin ningún compromiso.
        </div>

        <div class="cta-wrapper">
            <a href="{{ $loginUrl }}"
               class="cta-button"
               style="display:inline-block;background:#fa7070;color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;letter-spacing:0.02em;padding:14px 36px;border-radius:10px;">
                Acceder a Fakturalista &rarr;
            </a>
        </div>

        <hr class="divider">

        <p class="help-text">
            Empieza creando tu primer cliente, servicio, presupuesto o factura.
        </p>
        <p class="help-text">
            ¿Tienes alguna pregunta o necesitas ayuda? Escríbenos a
            <a href="mailto:contact@fakturalista.com">contact@fakturalista.com</a>
            y te responderemos en menos de 24 horas.
        </p>

    </div>

    <div class="footer">
        <p class="footer-brand"><span>Faktura</span>lista &mdash; Facturación para autónomos y empresas</p>
        <p class="footer-links">
            <a href="{{ url('/') }}">fakturalista.com</a>
            &nbsp;&middot;&nbsp;
            <a href="{{ url('/contact') }}">Soporte</a>
            &nbsp;&middot;&nbsp;
            <a href="{{ url('/pricing') }}">Planes</a>
        </p>
        <p style="font-size:11px;color:#d1d5db;margin-top:12px;">
            Recibes este email porque se ha creado una cuenta vinculada a {{ $ownerEmail }}.
        </p>
    </div>

</div>
</td></tr>
</table>
</div>
</body>
</html>
