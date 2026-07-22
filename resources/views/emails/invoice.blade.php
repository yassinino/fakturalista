<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Facture {{ $invoice->reference }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f7;font-family:Arial,Helvetica,sans-serif;color:#1a1a1a;">

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f4f7;padding:32px 0;">
  <tr>
    <td align="center">
      <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.06);">

        <!-- Header -->
        <tr>
          <td style="background:#E91E63;padding:28px 36px;">
            <p style="margin:0;font-size:11px;letter-spacing:0.08em;text-transform:uppercase;color:rgba(255,255,255,0.7);font-weight:600;">Facture</p>
            <p style="margin:6px 0 0;font-size:22px;font-weight:800;color:#ffffff;">{{ $invoice->reference }}</p>
            <p style="margin:6px 0 0;font-size:13px;color:rgba(255,255,255,0.75);">{{ $companyName }}</p>
          </td>
        </tr>

        <!-- Body -->
        <tr>
          <td style="padding:36px;">
            @if($customMessage)
            <p style="margin:0 0 24px;font-size:15px;color:#374151;line-height:1.7;">{!! nl2br(e($customMessage)) !!}</p>
            @else
            <p style="margin:0 0 16px;font-size:15px;color:#374151;">
              Bonjour <strong>{{ $customerName }}</strong>,
            </p>
            <p style="margin:0 0 24px;font-size:15px;color:#374151;line-height:1.6;">
              Veuillez trouver ci-joint votre facture <strong>{{ $invoice->reference }}</strong>.
            </p>
            @endif

            <!-- Amount highlight -->
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;">
              <tr>
                <td style="background:#fdf2f8;border-left:4px solid #E91E63;border-radius:4px;padding:16px 20px;">
                  <p style="margin:0 0 4px;font-size:12px;color:#9ca3af;letter-spacing:0.05em;text-transform:uppercase;font-weight:600;">Montant total dû</p>
                  <p style="margin:0;font-size:30px;font-weight:800;color:#E91E63;">
                    {{ number_format((float)$invoice->total, 2, ',', ' ') }}&nbsp;€
                  </p>
                </td>
              </tr>
            </table>

            <!-- Detail rows -->
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;border-top:1px solid #f0f0f0;">
              <tr>
                <td style="padding:10px 0;border-bottom:1px solid #f0f0f0;font-size:13px;color:#6b7280;">Date de la facture</td>
                <td style="padding:10px 0;border-bottom:1px solid #f0f0f0;font-size:13px;font-weight:600;color:#1a1a1a;text-align:right;">
                  {{ \Carbon\Carbon::parse($invoice->date)->format('d/m/Y') }}
                </td>
              </tr>
              @if($invoice->expiration_date)
              <tr>
                <td style="padding:10px 0;border-bottom:1px solid #f0f0f0;font-size:13px;color:#6b7280;">Date d'échéance</td>
                <td style="padding:10px 0;border-bottom:1px solid #f0f0f0;font-size:13px;font-weight:600;color:#E91E63;text-align:right;">
                  {{ \Carbon\Carbon::parse($invoice->expiration_date)->format('d/m/Y') }}
                </td>
              </tr>
              @endif
              @if($invoice->sub_total && $invoice->sub_total != $invoice->total)
              <tr>
                <td style="padding:10px 0;border-bottom:1px solid #f0f0f0;font-size:13px;color:#6b7280;">Sous-total HT</td>
                <td style="padding:10px 0;border-bottom:1px solid #f0f0f0;font-size:13px;font-weight:600;color:#1a1a1a;text-align:right;">
                  {{ number_format((float)$invoice->sub_total, 2, ',', ' ') }}&nbsp;€
                </td>
              </tr>
              @endif
            </table>

            @if(isset($paymentUrl) && $paymentUrl)
            <!-- Pay Now CTA -->
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;">
              <tr>
                <td align="center" style="background:#fdf2f8;border:1px solid #f9a8d4;border-radius:8px;padding:24px 20px;">
                  <p style="margin:0 0 6px;font-size:12px;color:#9ca3af;text-transform:uppercase;letter-spacing:0.07em;font-weight:600;">Paiement en ligne</p>
                  <p style="margin:0 0 18px;font-size:14px;color:#374151;">Réglez cette facture directement en ligne par carte bancaire.</p>
                  <a href="{{ $paymentUrl }}"
                     style="display:inline-block;background:#E91E63;color:#ffffff;font-weight:700;font-size:15px;padding:14px 36px;border-radius:6px;text-decoration:none;letter-spacing:0.01em;">
                    Payer maintenant →
                  </a>
                  <p style="margin:12px 0 0;font-size:11px;color:#9ca3af;">Paiement 100&nbsp;% sécurisé · Propulsé par Stripe</p>
                </td>
              </tr>
            </table>
            @endif

            <!-- PDF notice -->
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;">
              <tr>
                <td style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:12px 16px;font-size:13px;color:#374151;">
                  &#128206;&nbsp; La facture en PDF est jointe à cet e-mail.
                </td>
              </tr>
            </table>

            @if($invoice->note)
            <p style="margin:0 0 20px;font-size:13px;color:#6b7280;font-style:italic;line-height:1.6;">
              {{ $invoice->note }}
            </p>
            @endif

            @if(!$customMessage)
            <p style="margin:0 0 8px;font-size:14px;color:#374151;line-height:1.6;">
              Pour toute question concernant cette facture, n'hésitez pas à nous contacter.
            </p>
            <p style="margin:0;font-size:14px;color:#374151;">
              Cordialement,<br>
              <strong>{{ $companyName }}</strong>
            </p>
            @endif
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="background:#f8f8f8;border-top:1px solid #e8e8e8;padding:20px 36px;">
            <p style="margin:0;font-size:13px;font-weight:700;color:#1a1a1a;">{{ $companyName }}</p>
            @if($companyEmail || $companyPhone)
            <p style="margin:4px 0 0;font-size:12px;color:#9ca3af;">
              @if($companyEmail){{ $companyEmail }}@endif
              @if($companyEmail && $companyPhone) &middot; @endif
              @if($companyPhone){{ $companyPhone }}@endif
            </p>
            @endif
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>

</body>
</html>
