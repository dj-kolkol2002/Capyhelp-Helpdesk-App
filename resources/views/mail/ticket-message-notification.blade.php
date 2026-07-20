<!DOCTYPE html>
<html lang="pl">
    <head>
        <meta charset="utf-8">
        <title>Nowa odpowiedz w zgloszeniu</title>
    </head>
    <body style="margin:0;background:#f4f6f8;font-family:Arial,sans-serif;color:#1f2937;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f6f8;padding:24px;">
            <tr>
                <td align="center">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;">
                        <tr>
                            <td style="padding:24px 24px 12px;">
                                <p style="margin:0 0 8px;color:#2563eb;font-size:14px;font-weight:700;">Helpdesk System</p>
                                <h1 style="margin:0;color:#111827;font-size:22px;">Nowa odpowiedz w zgloszeniu {{ $ticketMessage->ticket->number }}</h1>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:12px 24px;">
                                <p style="margin:0 0 16px;font-size:15px;line-height:1.6;">
                                    {{ $ticketMessage->author_name }} dodal(a) nowa wiadomosc w sprawie:
                                    <strong>{{ $ticketMessage->ticket->subject }}</strong>
                                </p>
                                <div style="border-left:4px solid #2563eb;background:#eff6ff;padding:14px 16px;border-radius:4px;font-size:15px;line-height:1.6;white-space:pre-line;">{{ $ticketMessage->body }}</div>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:12px 24px 24px;">
                                <a href="{{ $ticketMessage->ticket->customerUrl() }}" style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;border-radius:6px;padding:10px 16px;font-size:14px;font-weight:700;">
                                    Otworz zgloszenie
                                </a>
                                <p style="margin:18px 0 0;color:#6b7280;font-size:12px;line-height:1.5;">
                                    Ta wiadomosc zostala wyslana automatycznie, poniewaz w zgloszeniu pojawila sie odpowiedz agenta.
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
</html>
