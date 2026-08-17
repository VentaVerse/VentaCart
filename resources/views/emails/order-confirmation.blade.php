<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation</title>
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
                        $country = $order->shipping_country_id ? \App\Models\Country::find($order->shipping_country_id) : null;
                        $countryName = $order->shipping_country_name ?? $country?->name;
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
                                Order Confirmation
                            </p>
                        </td>
                    </tr>

                    {{-- Order Info --}}
                    <tr>
                        <td style="padding: 32px 40px 16px 40px;">
                            <p style="margin: 0 0 4px 0; font-size: 14px; color: #999999;">
                                Order Number
                            </p>
                            <p style="margin: 0 0 16px 0; font-size: 22px; font-weight: 700; color: {{ $primaryColor }};">
                                #{{ $order->id }}
                            </p>
                            <p style="margin: 0; font-size: 14px; color: #999999;">
                                Date: {{ $order->created_at ? $order->created_at->format('F j, Y \a\t g:i A') : 'N/A' }}
                            </p>
                        </td>
                    </tr>

                    {{-- Greeting --}}
                    <tr>
                        <td style="padding: 16px 40px;">
                            <p style="margin: 0; font-size: 15px; color: #e8e8e8; line-height: 1.6;">
                                Hi {{ $order->first_name }}, thank you for your order! Here's a summary of what you purchased.
                            </p>
                        </td>
                    </tr>

                    {{-- Shipping Address --}}
                    <tr>
                        <td style="padding: 16px 40px 8px 40px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #222222; border-radius: 6px;">
                                <tr>
                                    <td style="padding: 20px 24px;">
                                        <p style="margin: 0 0 8px 0; font-size: 12px; font-weight: 700; color: #999999; text-transform: uppercase; letter-spacing: 1px;">
                                            Shipping Address
                                        </p>
                                        <p style="margin: 0; font-size: 14px; color: #e8e8e8; line-height: 1.6;">
                                            {{ $order->shipping_first_name }} {{ $order->shipping_last_name }}<br>
                                            {{ $order->shipping_address_1 }}
                                            @if ($order->shipping_barangay)<br>{{ $order->shipping_barangay }}@endif
                                            @if ($order->shipping_district)<br>{{ $order->shipping_district }}, {{ $order->shipping_city }}@else<br>{{ $order->shipping_city }}@endif
                                            @if ($order->shipping_state), {{ $order->shipping_state }}@endif
                                            @if ($order->shipping_postcode) {{ $order->shipping_postcode }}@endif
                                            @if ($countryName)<br>{{ $countryName }}@endif
                                        </p>
                                        @if ($order->shipping_phone)
                                            <p style="margin: 8px 0 0 0; font-size: 13px; color: #bbbbbb;">
                                                Phone: {{ $country && $country->phone_code ? '+' . $country->phone_code . ' ' : '' }}{{ $order->shipping_phone }}
                                            </p>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Payment Method --}}
                    <tr>
                        <td style="padding: 8px 40px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #222222; border-radius: 6px;">
                                <tr>
                                    <td style="padding: 20px 24px;">
                                        <p style="margin: 0 0 6px 0; font-size: 12px; font-weight: 700; color: #999999; text-transform: uppercase; letter-spacing: 1px;">
                                            Payment Method
                                        </p>
                                        <p style="margin: 0; font-size: 14px; color: #e8e8e8;">
                                            {{ $order->payment_method_label }}
                                        </p>
                                        @if (str_starts_with($order->payment_method, 'bank_transfer'))
                                            @php
                                                $bankAcct = null;
                                                if (preg_match('/^bank_transfer_(\d+)$/', $order->payment_method, $m)) {
                                                    $bankAcct = class_exists(\Extensions\PaymentBankTransfer\Models\BankAccount::class) ? \Extensions\PaymentBankTransfer\Models\BankAccount::find((int) $m[1]) : null;
                                                }
                                            @endphp
                                            @if ($bankAcct)
                                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top: 12px; border-top: 1px solid #333;">
                                                    <tr>
                                                        <td style="padding: 8px 0 2px 0; font-size: 13px; color: #999;">Bank:</td>
                                                        <td style="padding: 8px 0 2px 0; font-size: 13px; color: #e8e8e8; text-align: right;">{{ $bankAcct->bank_name }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td style="padding: 2px 0; font-size: 13px; color: #999;">Account Name:</td>
                                                        <td style="padding: 2px 0; font-size: 13px; color: #e8e8e8; text-align: right;">{{ $bankAcct->account_name }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td style="padding: 2px 0 8px 0; font-size: 13px; color: #999;">Account Number:</td>
                                                        <td style="padding: 2px 0 8px 0; font-size: 13px; color: #e8e8e8; text-align: right;">{{ $bankAcct->account_number }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td colspan="2" style="padding: 8px 0 0 0; font-size: 13px; color: #999; border-top: 1px solid #333;">Instructions:</td>
                                                    </tr>
                                                    <tr>
                                                        <td colspan="2" style="padding: 4px 0 0 0; font-size: 13px; color: #e8e8e8; line-height: 1.5;">
                                                            @if ($bankAcct->instructions)
                                                                {!! nl2br(e($bankAcct->instructions)) !!}
                                                            @else
                                                                <span style="color: #666;">—</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                </table>
                                                @if ($bankAcct->qr_image)
                                                    <div style="margin-top: 12px; text-align: center;">
                                                        <img src="{{ url('/storage/' . $bankAcct->qr_image) }}" alt="QR Code" style="width: 100%; max-width: 240px; height: auto; border-radius: 6px; display: inline-block;">
                                                    </div>
                                                @endif
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Order Note --}}
                    <tr>
                        <td style="padding: 8px 40px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #222222; border-radius: 6px;">
                                <tr>
                                    <td style="padding: 20px 24px;">
                                        <p style="margin: 0 0 8px 0; font-size: 12px; font-weight: 700; color: #999999; text-transform: uppercase; letter-spacing: 1px;">
                                            Order Note
                                        </p>
                                        <p style="margin: 0; font-size: 14px; color: #e8e8e8; line-height: 1.6;">
                                            @if ($order->comment)
                                                {!! nl2br(e($order->comment)) !!}
                                            @else
                                                <span style="color: #666;">—</span>
                                            @endif
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Items Table --}}
                    <tr>
                        <td style="padding: 16px 40px;">
                            <p style="margin: 0 0 12px 0; font-size: 12px; font-weight: 700; color: #999999; text-transform: uppercase; letter-spacing: 1px;">
                                Items Ordered
                            </p>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
                                {{-- Table Header --}}
                                <tr>
                                    <td style="padding: 10px 8px; border-bottom: 1px solid #333333; width: 58px;">
                                    </td>
                                    <td style="padding: 10px 12px; font-size: 12px; font-weight: 700; color: #999999; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid #333333;">
                                        Item
                                    </td>
                                    <td style="padding: 10px 12px; font-size: 12px; font-weight: 700; color: #999999; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid #333333; text-align: center; width: 50px;">
                                        Qty
                                    </td>
                                    <td style="padding: 10px 12px; font-size: 12px; font-weight: 700; color: #999999; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid #333333; text-align: right; width: 90px;">
                                        Price
                                    </td>
                                    <td style="padding: 10px 12px; font-size: 12px; font-weight: 700; color: #999999; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid #333333; text-align: right; width: 90px;">
                                        Total
                                    </td>
                                </tr>
                                {{-- Items --}}
                                @foreach ($order->products as $item)
                                <tr>
                                    <td style="padding: 8px; border-bottom: 1px solid #2a2a2a; width: 58px; vertical-align: middle;">
                                        @if ($item->image)
                                            <img src="{{ url('/storage/' . $item->image) }}" alt="" width="50" height="50" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px; display: block;">
                                        @endif
                                    </td>
                                    <td style="padding: 12px; font-size: 14px; color: #e8e8e8; border-bottom: 1px solid #2a2a2a;">
                                        {{ $item->name }}
                                        @if ($item->variant_label)
                                            <br><span style="font-size: 12px; color: #999999;">{{ $item->variant_label }}</span>
                                        @endif
                                    </td>
                                    <td style="padding: 12px; font-size: 14px; color: #e8e8e8; border-bottom: 1px solid #2a2a2a; text-align: center;">
                                        {{ $item->quantity }}
                                    </td>
                                    <td style="padding: 12px; font-size: 14px; color: #e8e8e8; border-bottom: 1px solid #2a2a2a; text-align: right;">
                                        {{ number_format($item->price, 2) }}
                                    </td>
                                    <td style="padding: 12px; font-size: 14px; color: #e8e8e8; border-bottom: 1px solid #2a2a2a; text-align: right;">
                                        {{ number_format($item->total, 2) }}
                                    </td>
                                </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>

                    {{-- Totals --}}
                    <tr>
                        <td style="padding: 8px 40px 24px 40px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding: 8px 12px; font-size: 14px; color: #999999; text-align: right;">
                                        Subtotal:
                                    </td>
                                    <td style="padding: 8px 12px; font-size: 14px; color: #e8e8e8; text-align: right; width: 100px;">
                                        {{ number_format($order->subtotal, 2) }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; font-size: 14px; color: #999999; text-align: right;">
                                        Shipping @if ($order->courier_label)({{ $order->courier_label }})@endif:
                                    </td>
                                    <td style="padding: 8px 12px; font-size: 14px; color: #e8e8e8; text-align: right; width: 100px;">
                                        @if ($order->shipping_cost > 0)
                                            {{ number_format($order->shipping_cost, 2) }}
                                        @else
                                            <span style="color: #4ade80;">FREE</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 12px 8px 12px; font-size: 18px; font-weight: 700; color: {{ $primaryColor }}; text-align: right; border-top: 2px solid #333333;">
                                        Total
                                    </td>
                                    <td style="padding: 12px 12px 8px 12px; font-size: 18px; font-weight: 700; color: {{ $primaryColor }}; text-align: right; width: 100px; border-top: 2px solid #333333;">
                                        {{ number_format($order->total, 2) }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding: 24px 40px 32px 40px; border-top: 1px solid #333333; text-align: center;">
                            <p style="margin: 0 0 8px 0; font-size: 16px; font-weight: 700; color: {{ $primaryColor }};">
                                Thank you for your order!
                            </p>
                            <p style="margin: 0; font-size: 13px; color: #777777;">
                                If you have any questions, please reply to this email or contact our support team.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
