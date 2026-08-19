<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f4f4f5;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:24px 0;">
    <tr>
        <td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:4px;">
                @if ($logo)
                    <tr><td style="padding:24px 24px 0;" align="center"><img src="{{ $logo }}" alt="{{ $storeName }}" style="max-height:48px;"></td></tr>
                @endif
                <tr>
                    <td style="padding:24px;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#1a1a1a;">
                        {!! $body !!}
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px 24px 24px;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.5;color:#777777;border-top:1px solid #eeeeee;">
                        {{ $storeName }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
