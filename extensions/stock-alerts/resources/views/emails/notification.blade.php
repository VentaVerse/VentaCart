<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Back In Stock</title>
</head>
<body style="margin: 0; padding: 0; background-color: #111111; font-family: Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #111111;">
        <tr>
            <td align="center" style="padding: 24px 16px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; width: 100%; background-color: #1a1a1a; border-radius: 8px; overflow: hidden;">

                    {{-- Header --}}
                    @php
                        $storeNameSetting = \App\Models\Setting::where('group', 'identity')->where('key', 'site_name')->value('value') ?? config('app.name', 'Venta');
                        $storeLogo = \App\Models\Setting::where('group', 'identity')->where('key', 'logo')->value('value');
                        $primaryColor = \App\Models\Setting::where('group', 'colors')->where('key', 'primary')->value('value') ?? '#BE3144';
                    @endphp
                    <tr>
                        <td style="background-color: #1a1a1a; border-bottom: 2px solid {{ $primaryColor }}; padding: 32px 40px; text-align: center;">
                            @if ($storeLogo)
                                <img src="{{ url('/storage/' . $storeLogo) }}" alt="{{ $storeNameSetting }}" style="max-height: 48px; max-width: 240px; margin-bottom: 8px;">
                            @else
                                <h1 style="margin: 0 0 8px 0; font-size: 28px; font-weight: 700; letter-spacing: 1px; color: #ffffff;">
                                    {{ $storeNameSetting }}
                                </h1>
                            @endif
                            <p style="margin: 0; font-size: 16px; color: #bbbbbb; text-transform: uppercase; letter-spacing: 2px;">
                                Back In Stock
                            </p>
                        </td>
                    </tr>

                    {{-- Body Content --}}
                    <tr>
                        <td style="padding: 32px 40px;">
                            <div style="font-size: 15px; color: #e8e8e8; line-height: 1.7;">
                                {!! $body !!}
                            </div>
                        </td>
                    </tr>

                    {{-- CTA Button --}}
                    <tr>
                        <td style="padding: 0 40px 32px 40px;" align="center">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="background-color: {{ $primaryColor }}; border-radius: 6px;">
                                        <a href="{{ $productUrl }}" target="_blank" style="display: inline-block; padding: 14px 40px; font-size: 16px; font-weight: 700; color: #ffffff; text-decoration: none; text-transform: uppercase; letter-spacing: 1px;">
                                            SHOP NOW
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding: 24px 40px 32px 40px; border-top: 1px solid #333333; text-align: center;">
                            <p style="margin: 0; font-size: 13px; color: #777777;">
                                &copy; {{ date('Y') }} {{ $storeName }}. All rights reserved.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
