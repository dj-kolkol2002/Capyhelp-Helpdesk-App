<!DOCTYPE html>
<html lang="pl">
    <head>
        <meta charset="utf-8">
        <title>Przypisano zgloszenie</title>
    </head>
    <body style="margin:0;background:#f4f6f8;font-family:Arial,sans-serif;color:#1f2937;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f6f8;padding:24px;">
            <tr>
                <td align="center">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;">
                        <tr>
                            <td style="padding:24px 24px 12px;">
                                <p style="margin:0 0 8px;color:#2563eb;font-size:14px;font-weight:700;">CAPYHELP</p>
                                <h1 style="margin:0;color:#111827;font-size:22px;">Przypisano do Ciebie zgloszenie {{ $ticket->number }}</h1>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:12px 24px;">
                                <p style="margin:0 0 16px;font-size:15px;line-height:1.6;">
                                    Zgloszenie od <strong>{{ $ticket->requester_name }}</strong> trafilo do Twojej kolejki:
                                    <strong>{{ $ticket->subject }}</strong>
                                </p>
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="font-size:14px;line-height:1.6;border-collapse:collapse;">
                                    <tr>
                                        <td style="padding:6px 0;color:#6b7280;">Status</td>
                                        <td style="padding:6px 0;text-align:right;font-weight:700;">{{ $ticket->status }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:6px 0;color:#6b7280;">Priorytet</td>
                                        <td style="padding:6px 0;text-align:right;font-weight:700;">{{ $ticket->priority }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:6px 0;color:#6b7280;">Kanal</td>
                                        <td style="padding:6px 0;text-align:right;font-weight:700;">{{ $ticket->channel }}</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:12px 24px 24px;">
                                <a href="{{ route('tickets.show', $ticket) }}" style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;border-radius:6px;padding:10px 16px;font-size:14px;font-weight:700;">
                                    Otworz zgloszenie
                                </a>
                                <p style="margin:18px 0 0;color:#6b7280;font-size:12px;line-height:1.5;">
                                    Otrzymujesz te wiadomosc, poniewaz masz wlaczone powiadomienia o przypisaniu ticketu.
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
</html>
