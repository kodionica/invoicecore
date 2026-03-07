<!doctype html>
<html lang="sr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Faktura</title>
</head>
<body style="margin:0; padding:0; font-family: Arial, sans-serif; color:#111;">
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#f7f7f7; padding:24px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" role="presentation" style="background:#ffffff; border-radius:8px; overflow:hidden;">
                    <tr>
                        <td style="padding:24px 28px 12px;">
                            <h1 style="margin:0 0 8px; font-size:20px;">Faktura #{{ $invoice->invoice_number }}</h1>
                            <p style="margin:0; color:#555;">Poštovani {{ $client->name }},</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 28px 16px;">
                            <p style="margin:0; color:#333; line-height:1.5;">
                                U prilogu se nalazi PDF faktura. Ako imate pitanja, slobodno nas kontaktirajte.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 28px 24px;">
                            <p style="margin:0; color:#333;">Pozdrav,</p>
                            <p style="margin:4px 0 0; color:#333; font-weight:bold;">{{ $company->name }}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
