<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Contact Message</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f5; font-family: Arial, Helvetica, sans-serif;">
    @php
        $storeName = \App\Models\Setting::where('group', 'identity')->where('key', 'site_name')->value('value') ?? config('app.name', 'Venta');
        $storeLogo = \App\Models\Setting::where('group', 'identity')->where('key', 'logo')->value('value');
        $primaryColor = \App\Models\Setting::where('group', 'colors')->where('key', 'primary')->value('value') ?? '#1f2937';
    @endphp
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f5;">
        <tr>
            <td align="center" style="padding: 24px 16px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #e4e4e7;">

                    <tr>
                        <td style="background-color: #ffffff; border-bottom: 2px solid {{ $primaryColor }}; padding: 28px 40px; text-align: center;">
                            @if ($storeLogo)
                                <img src="{{ url('/storage/' . $storeLogo) }}" alt="{{ $storeName }}" style="max-height: 48px; max-width: 240px; margin-bottom: 8px;">
                            @else
                                <h1 style="margin: 0 0 8px 0; font-size: 24px; font-weight: 700; letter-spacing: 1px; color: #18181b;">
                                    {{ $storeName }}
                                </h1>
                            @endif
                            <p style="margin: 0; font-size: 13px; color: #71717a; text-transform: uppercase; letter-spacing: 2px;">
                                New Contact Message
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 32px 40px 8px 40px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size: 14px; color: #27272a;">
                                <tr>
                                    <td style="padding: 6px 0; width: 90px; color: #71717a;">From</td>
                                    <td style="padding: 6px 0;"><strong>{{ $data['name'] }}</strong></td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; color: #71717a;">Email</td>
                                    <td style="padding: 6px 0;">
                                        <a href="mailto:{{ $data['email'] }}" style="color: {{ $primaryColor }}; text-decoration: none;">{{ $data['email'] }}</a>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; color: #71717a;">Subject</td>
                                    <td style="padding: 6px 0;">{{ $data['subject'] }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 16px 40px 8px 40px;">
                            <hr style="border: none; border-top: 1px solid #e4e4e7; margin: 0;">
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 8px 40px 32px 40px;">
                            <div style="font-size: 15px; color: #27272a; line-height: 1.7; white-space: pre-wrap;">{{ $data['message'] }}</div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 20px 40px 28px 40px; border-top: 1px solid #e4e4e7; text-align: center;">
                            <p style="margin: 0; font-size: 12px; color: #a1a1aa;">
                                Sent via the contact form on {{ $storeName }}.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
