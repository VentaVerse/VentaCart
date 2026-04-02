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
                    @php
                        $storeName = \App\Models\Setting::where('group', 'identity')->where('key', 'site_name')->value('value') ?? config('app.name', 'Venta');
                        $storeLogo = \App\Models\Setting::where('group', 'identity')->where('key', 'logo')->value('value');
                        $primaryColor = \App\Models\Setting::where('group', 'colors')->where('key', 'primary')->value('value') ?? '#BE3144';
                    @endphp
                    <tr>
                        <td style="background-color: #1a1a1a; border-bottom: 2px solid {{ $primaryColor }}; padding: 32px 40px; text-align: center;">
                            @if ($storeLogo)
                                <img src="{{ url('/storage/' . $storeLogo) }}" alt="{{ $storeName }}" style="max-height: 48px; max-width: 240px; margin-bottom: 8px;">
                            @else
                                <h1 style="margin: 0 0 8px 0; font-size: 28px; font-weight: 700; letter-spacing: 1px; color: #ffffff;">
                                    {{ $storeName }}
                                </h1>
                            @endif
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
                                Your account email: <strong style="color: {{ $primaryColor }};">{{ $customer->email }}</strong>
                            </p>

                            {{-- CTA Button --}}
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin: 0 auto;">
                                <tr>
                                    <td style="background-color: {{ $primaryColor }}; border-radius: 6px;">
                                        <a href="{{ route('account.dashboard') }}" style="display: inline-block; padding: 12px 32px; font-size: 14px; font-weight: 700; color: #ffffff; text-decoration: none; text-transform: uppercase; letter-spacing: 1px;">
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
