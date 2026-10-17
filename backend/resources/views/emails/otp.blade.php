<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kode OTP Verifikasi LaundryKu</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f8fafc; padding: 40px 15px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" style="max-width: 520px; background-color: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);" cellspacing="0" cellpadding="0">
                    <!-- Header -->
                    <tr>
                        <td style="background-color: #0f172a; padding: 24px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 20px; font-weight: 700; letter-spacing: 0.5px;">
                                Laundry<span style="color: #14b8a6;">Ku</span>
                            </h1>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 32px 28px;">
                            <h2 style="margin: 0 0 12px 0; font-size: 18px; font-weight: 700; color: #0f172a;">
                                Kode Verifikasi OTP
                            </h2>
                            <p style="margin: 0 0 20px 0; font-size: 14px; line-height: 1.6; color: #475569;">
                                Halo, gunakan kode One-Time Password (OTP) berikut untuk menyelesaikan verifikasi akun LaundryKu Anda:
                            </p>

                            <!-- OTP Code Box -->
                            <div style="background-color: #f0fdfa; border: 1.5px solid #5eead4; border-radius: 10px; padding: 18px; text-align: center; margin: 24px 0;">
                                <span style="font-family: 'Courier New', Courier, monospace; font-size: 32px; font-weight: 800; letter-spacing: 8px; color: #0f766e; display: inline-block;">
                                    {{ $otpCode }}
                                </span>
                            </div>

                            <p style="margin: 0 0 16px 0; font-size: 13px; color: #64748b; text-align: center;">
                                Kode verifikasi ini berlaku selama <strong>{{ $validityMinutes }} menit</strong>.
                            </p>

                            <!-- Warning Box -->
                            <div style="background-color: #fffbeb; border-left: 4px solid #f59e0b; padding: 12px 16px; border-radius: 4px; margin-top: 24px;">
                                <p style="margin: 0; font-size: 12px; line-height: 1.5; color: #92400e;">
                                    <strong>Peringatan Keamanan:</strong> Jangan berikan kode OTP ini kepada siapa pun, termasuk pihak yang mengaku dari LaundryKu. Tim kami tidak pernah meminta kode OTP Anda.
                                </p>
                            </div>

                            <p style="margin: 24px 0 0 0; font-size: 12px; line-height: 1.5; color: #94a3b8;">
                                Jika Anda tidak merasa melakukan pendaftaran atau permintaan ini, silakan abaikan email ini.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8fafc; border-top: 1px solid #e2e8f0; padding: 16px 24px; text-align: center;">
                            <p style="margin: 0; font-size: 11px; color: #94a3b8;">
                                &copy; {{ date('Y') }} LaundryKu. Hak cipta dilindungi.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
