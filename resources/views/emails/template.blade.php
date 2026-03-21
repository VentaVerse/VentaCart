<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? '' }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #111111; font-family: Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #111111;">
        <tr>
            <td align="center" style="padding: 24px 16px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; width: 100%; background-color: #1a1a1a; border-radius: 8px; overflow: hidden;">

                    {{-- Header --}}
                    <tr>
                        <td style="background-color: #1a1a1a; border-bottom: 2px solid #F5A623; padding: 32px 40px; text-align: center;">
                            <h1 style="margin: 0; font-size: 28px; font-weight: 700; letter-spacing: 1px;">
                                <span style="color: #F5A623;">New</span><span style="color: #ffffff;">Gear</span><span style="color: #F5A623;">Day</span>
                            </h1>
                        </td>
                    </tr>

                    {{-- Body Content --}}
                    <tr>
                        <td style="padding: 32px 40px; font-size: 15px; color: #e8e8e8; line-height: 1.6;">
                            {!! $body !!}
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding: 24px 40px 32px 40px; border-top: 1px solid #333333; text-align: center;">
                            <p style="margin: 0 0 8px 0; font-size: 13px; color: #777777;">
                                If you have any questions, please reply to this email or contact our support team.
                            </p>
                            <p style="margin: 0; font-size: 12px; color: #555555;">
                                &copy; {{ date('Y') }} NewGearDay. All rights reserved.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
