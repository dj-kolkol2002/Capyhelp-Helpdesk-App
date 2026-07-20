<!DOCTYPE html>
<html lang="pl">
    <head>
        <meta charset="utf-8">
        <title>Przyjelismy zgloszenie</title>
    </head>
    <body style="margin:0;background:#f4f6f8;font-family:Arial,sans-serif;color:#1f2937;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f6f8;padding:24px;">
            <tr>
                <td align="center">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;">
                        <tr>
                            <td style="padding:24px 24px 12px;">
                                <p style="margin:0 0 8px;color:#2563eb;font-size:14px;font-weight:700;">CAPYHELP</p>
                                <h1 style="margin:0;color:#111827;font-size:22px;">Przyjelismy Twoje zgloszenie {{ $ticket->number }}</h1>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:12px 24px;">
                                <p style="margin:0 0 16px;font-size:15px;line-height:1.6;">
                                    Dziekujemy za kontakt. Twoja sprawa <strong>{{ $ticket->subject }}</strong> trafila do kolejki obslugi.
                                </p>
                                <p style="margin:0;font-size:15px;line-height:1.6;">
                                    Mozesz wrocic do rozmowy, sprawdzic status i doslac kolejne informacje przez prywatny link ponizej.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:12px 24px 24px;">
                                <a href="{{ $ticket->customerUrl() }}" style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;border-radius:6px;padding:10px 16px;font-size:14px;font-weight:700;">
                                    Otworz swoje zgloszenie
                                </a>
                                <p style="margin:18px 0 0;color:#6b7280;font-size:12px;line-height:1.5;">
                                    Zachowaj ten link. Daje dostep do historii tej sprawy bez zakladania konta.
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
</html>
