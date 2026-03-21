<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome</title>
</head>
<body style="margin: 0; padding: 0; background-color: #111111; font-family: Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #111111;">
        <tr>
            <td align="center" style="padding: 24px 16px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; width: 100%; background-color: #1a1a1a; border-radius: 8px; overflow: hidden;">

                    {{-- Header --}}
                    <tr>
                        <td style="background-color: #1a1a1a; border-bottom: 2px solid #F5A623; padding: 32px 40px; text-align: center;">
                            <h1 style="margin: 0 0 8px 0; font-size: 28px; font-weight: 700; letter-spacing: 1px;">
                                <span style="color: #F5A623;">New</span><span style="color: #ffffff;">Gear</span><span style="color: #F5A623;">Day</span>
                            </h1>
                            <p style="margin: 0; font-size: 16px; color: #bbbbbb; text-transform: uppercase; letter-spacing: 2px;">
                                Welcome
                            </p>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding: 32px 40px;">
                            <p style="margin: 0 0 16px 0; font-size: 16px; color: #dddddd;">
                                Hi {{ $customer->first_name }},
                            </p>
                            <p style="margin: 0 0 16px 0; font-size: 14px; color: #bbbbbb; line-height: 1.6;">
                                Thank you for creating an account with {{ config('app.name') }}. You can now track your orders, manage your addresses, and enjoy a faster checkout experience.
                            </p>
                            <p style="margin: 0 0 24px 0; font-size: 14px; color: #bbbbbb; line-height: 1.6;">
                                Your account email: <strong style="color: #F5A623;">{{ $customer->email }}</strong>
                            </p>

                            {{-- CTA Button --}}
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin: 0 auto;">
                                <tr>
                                    <td style="background-color: #F5A623; border-radius: 6px;">
                                        <a href="{{ route('account.dashboard') }}" style="display: inline-block; padding: 12px 32px; font-size: 14px; font-weight: 700; color: #000000; text-decoration: none; text-transform: uppercase; letter-spacing: 1px;">
                                            Visit Your Account
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding: 24px 40px; border-top: 1px solid #333333; text-align: center;">
                            <p style="margin: 0; font-size: 12px; color: #666666;">
                                &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
