<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset your Fakturalista password</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;-webkit-font-smoothing:antialiased;">

  <table width="100%" cellpadding="0" cellspacing="0" border="0" style="padding:40px 16px;">
    <tr>
      <td align="center">

        <!-- Card -->
        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 6px rgba(0,0,0,0.05),0 10px 40px rgba(0,0,0,0.08);">

          <!-- Header with brand gradient -->
          <tr>
            <td style="background:linear-gradient(135deg,#E91E63 0%,#c2185b 100%);padding:28px 40px;text-align:center;">
              <table cellpadding="0" cellspacing="0" border="0" style="margin:0 auto;">
                <tr>
                  <td style="vertical-align:middle;">
                    <span style="display:inline-block;background:rgba(255,255,255,0.18);border-radius:8px;width:36px;height:36px;text-align:center;line-height:36px;font-size:18px;font-weight:900;color:#fff;margin-right:10px;vertical-align:middle;">F</span>
                    <span style="font-size:19px;font-weight:800;color:#fff;vertical-align:middle;letter-spacing:-0.3px;">Fakturalista</span>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Body -->
          <tr>
            <td style="padding:40px 40px 32px;">

              <h1 style="margin:0 0 8px;font-size:22px;font-weight:800;color:#0f172a;letter-spacing:-0.4px;">
                Reset your password
              </h1>
              <p style="margin:0 0 24px;font-size:14px;color:#64748b;line-height:1.65;">
                Hi {{ $user->name ?? 'there' }},
              </p>
              <p style="margin:0 0 28px;font-size:15px;color:#374151;line-height:1.7;">
                We received a request to reset the password for your Fakturalista account
                linked to <strong style="color:#0f172a;">{{ $user->email }}</strong>.
                Click the button below to choose a new password.
                This link will expire in <strong>60&nbsp;minutes</strong>.
              </p>

              <!-- CTA -->
              <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:32px 0;">
                <tr>
                  <td align="center">
                    <a
                      href="{{ $resetUrl }}"
                      style="display:inline-block;padding:14px 36px;background:linear-gradient(135deg,#E91E63 0%,#c2185b 100%);color:#ffffff;text-decoration:none;border-radius:12px;font-size:15px;font-weight:700;letter-spacing:0.01em;box-shadow:0 4px 14px rgba(233,30,99,0.3);"
                    >
                      Reset my password
                    </a>
                  </td>
                </tr>
              </table>

              <!-- Security note -->
              <div style="background:#f8fafc;border-radius:10px;padding:16px 18px;margin-bottom:24px;">
                <p style="margin:0;font-size:13px;color:#64748b;line-height:1.65;">
                  🔒 If you didn't request a password reset, you can safely ignore this email.
                  Your password will remain unchanged.
                </p>
              </div>

              <!-- Fallback link -->
              <p style="margin:0;font-size:12.5px;color:#94a3b8;line-height:1.7;">
                If the button above doesn't work, copy and paste this link into your browser:<br>
                <a href="{{ $resetUrl }}" style="color:#E91E63;word-break:break-all;font-size:12px;">{{ $resetUrl }}</a>
              </p>

            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="padding:20px 40px;background:#f8fafc;border-top:1px solid #f1f5f9;text-align:center;">
              <p style="margin:0;font-size:12px;color:#94a3b8;line-height:1.6;">
                © {{ date('Y') }} Fakturalista · All rights reserved<br>
                You're receiving this email because a password reset was requested for your account.
              </p>
            </td>
          </tr>

        </table>
        <!-- END Card -->

      </td>
    </tr>
  </table>

</body>
</html>
