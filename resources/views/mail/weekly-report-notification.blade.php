<!DOCTYPE html>
<html lang="pl">
    <head>
        <meta charset="utf-8">
        <title>Tygodniowy raport</title>
    </head>
    <body style="margin:0;background:#f4f6f8;font-family:Arial,sans-serif;color:#1f2937;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f6f8;padding:24px;">
            <tr>
                <td align="center">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:720px;background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;">
                        <tr>
                            <td style="padding:24px 24px 12px;">
                                <p style="margin:0 0 8px;color:#2563eb;font-size:14px;font-weight:700;">CAPYHELP</p>
                                <h1 style="margin:0;color:#111827;font-size:22px;">Raport tygodniowy</h1>
                                <p style="margin:8px 0 0;color:#6b7280;font-size:14px;">{{ $report['range_label'] }}</p>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:12px 24px;">
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                    <tr>
                                        @foreach ($report['kpis'] as $item)
                                            <td style="width:25%;padding:6px;">
                                                <div style="border:1px solid #e5e7eb;border-radius:8px;padding:12px;background:#f9fafb;">
                                                    <p style="margin:0;color:#6b7280;font-size:12px;">{{ $item['label'] }}</p>
                                                    <p style="margin:6px 0 0;color:#111827;font-size:24px;font-weight:700;">{{ $item['value'] }}</p>
                                                </div>
                                            </td>
                                        @endforeach
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:12px 24px;">
                                <h2 style="margin:0 0 10px;color:#111827;font-size:16px;">Aktywne zgłoszenia według priorytetu</h2>
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
                                    @foreach ($report['priorities'] as $label => $value)
                                        <tr>
                                            <td style="border-top:1px solid #e5e7eb;padding:8px 0;color:#374151;font-size:14px;">{{ $label }}</td>
                                            <td align="right" style="border-top:1px solid #e5e7eb;padding:8px 0;color:#111827;font-size:14px;font-weight:700;">{{ $value }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:12px 24px 24px;">
                                <a href="{{ route('dashboard') }}" style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;border-radius:6px;padding:10px 16px;font-size:14px;font-weight:700;">
                                    Otworz dashboard
                                </a>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
</html>
