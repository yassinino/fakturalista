<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ __('emails.invoice_due_reminder.subject', ['reference' => $invoice->reference]) }}</title>
    <!--[if mso]>
    <noscript>
        <xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml>
    </noscript>
    <![endif]-->
    <style>
        /* Reset */
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

        /* Layout */
        .email-wrapper  { background-color: #f4f5f7; padding: 40px 20px; width: 100%; }
        .email-card     { background: #ffffff; border-radius: 12px; max-width: 580px; margin: 0 auto; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }

        /* Header */
        .header         { background: #1a1a2e; padding: 32px 40px; text-align: center; }
        .header-logo    { color: #fa7070; font-size: 22px; font-weight: 700; letter-spacing: -0.5px; text-decoration: none; }
        .header-logo span { color: #ffffff; }

        /* Hero */
        .hero           { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); padding: 40px 40px 36px; text-align: center; }
        .hero-title     { color: #ffffff; font-size: 26px; font-weight: 700; letter-spacing: -0.4px; line-height: 1.25; margin-bottom: 10px; }
        .hero-subtitle  { color: rgba(255,255,255,0.85); font-size: 15px; line-height: 1.6; }

        /* Body */
        .body           { padding: 36px 40px; }
        .greeting       { font-size: 16px; color: #374151; line-height: 1.6; margin-bottom: 24px; }
        .greeting strong { color: #111827; }

        /* Invoice summary card */
        .invoice-card   { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 10px; padding: 18px 20px; margin-bottom: 28px; }
        .invoice-row    { font-size: 14px; color: #374151; padding: 5px 0; }
        .invoice-row strong { color: #111827; }
        .invoice-amount { font-size: 22px; font-weight: 800; color: #111827; margin-top: 4px; }

        /* CTA */
        .cta-wrapper    { text-align: center; margin: 4px 0 32px; }
        .cta-button     {
            display: inline-block;
            background: #f59e0b;
            color: #ffffff !important;
            text-decoration: none !important;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 0.02em;
            padding: 14px 36px;
            border-radius: 10px;
        }

        /* Divider */
        .divider        { border: none; border-top: 1px solid #e5e7eb; margin: 28px 0; }

        /* Help text */
        .help-text      { font-size: 14px; color: #6b7280; line-height: 1.7; margin-bottom: 8px; }
        .help-text a    { color: #f59e0b; text-decoration: none; font-weight: 500; }

        /* Footer */
        .footer         { background: #f8fafc; border-top: 1px solid #e5e7eb; padding: 24px 40px; text-align: center; }
        .footer-brand   { font-size: 13px; font-weight: 700; color: #6b7280; margin-bottom: 8px; }
        .footer-brand span { color: #fa7070; }
        .footer-links   { font-size: 12px; color: #9ca3af; }
        .footer-links a { color: #9ca3af; text-decoration: underline; }

        /* Responsive */
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

    {{-- ── Header ────────────────────────────────────── --}}
    <div class="header">
        <div class="header-logo">Faktura<span>lista</span></div>
    </div>

    {{-- ── Hero ─────────────────────────────────────── --}}
    <div class="hero">
        <table width="56" cellpadding="0" cellspacing="0" style="margin:0 auto 20px;" role="presentation">
            <tr><td width="56" height="56" bgcolor="#ffffff33" style="border-radius:50%;text-align:center;vertical-align:middle;">
                <span style="font-size:26px;line-height:56px;display:block;">⏰</span>
            </td></tr>
        </table>
        <div class="hero-title">
            {{ __('emails.invoice_due_reminder.hero_title', ['days' => $daysUntilDue]) }}
        </div>
        <div class="hero-subtitle">{{ __('emails.invoice_due_reminder.hero_subtitle') }}</div>
    </div>

    {{-- ── Body ─────────────────────────────────────── --}}
    <div class="body">

        <p class="greeting">
            {{ __('emails.invoice_due_reminder.greeting_hello') }} <strong>{{ $tenant->owner_name }}</strong>,<br><br>
            {{ __('emails.invoice_due_reminder.body', ['reference' => $invoice->reference, 'client' => $customerName, 'days' => $daysUntilDue]) }}
        </p>

        <div class="invoice-card">
            <div class="invoice-row">{{ __('emails.invoice_due_reminder.label_client') }}: <strong>{{ $customerName }}</strong></div>
            <div class="invoice-row">{{ __('emails.invoice_due_reminder.label_due_date') }}: <strong>{{ \Carbon\Carbon::parse($invoice->expiration_date)->translatedFormat('d/m/Y') }}</strong></div>
            <div class="invoice-amount">{{ $formattedTotal }}</div>
        </div>

        {{-- CTA button --}}
        <div class="cta-wrapper">
            <a href="{{ $invoiceUrl }}"
               class="cta-button"
               style="display:inline-block;background:#f59e0b;color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;letter-spacing:0.02em;padding:14px 36px;border-radius:10px;">
                {{ __('emails.invoice_due_reminder.cta_button') }}
            </a>
        </div>

        <hr class="divider">

        {{-- Help --}}
        <p class="help-text">
            {{ __('emails.invoice_due_reminder.help_question') }}
        </p>
        <p class="help-text">
            📧 {{ __('emails.invoice_due_reminder.help_contact') }} <a href="mailto:contact@fakturalista.com">contact@fakturalista.com</a>
            {{ __('emails.invoice_due_reminder.help_response') }}
        </p>

    </div>

    {{-- ── Footer ───────────────────────────────────── --}}
    <div class="footer">
        <p class="footer-brand"><span>Faktura</span>lista &mdash; {{ __('emails.invoice_due_reminder.footer_tagline') }}</p>
        <p class="footer-links">
            <a href="{{ url('/') }}">fakturalista.com</a>
            &nbsp;&middot;&nbsp;
            <a href="{{ url('/contact') }}">{{ __('emails.invoice_due_reminder.footer_support') }}</a>
        </p>
        <p style="font-size:11px;color:#d1d5db;margin-top:12px;">
            {{ __('emails.invoice_due_reminder.footer_note') }}
        </p>
    </div>

</div>
</td></tr>
</table>
</div>
</body>
</html>
