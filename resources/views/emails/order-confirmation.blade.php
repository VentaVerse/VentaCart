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
                    <tr>
                        <td style="background-color: #1a1a1a; border-bottom: 2px solid #F5A623; padding: 32px 40px; text-align: center;">
                            <h1 style="margin: 0 0 8px 0; font-size: 28px; font-weight: 700; letter-spacing: 1px;">
                                <span style="color: #F5A623;">New</span><span style="color: #ffffff;">Gear</span><span style="color: #F5A623;">Day</span>
                            </h1>
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
                            <p style="margin: 0 0 16px 0; font-size: 22px; font-weight: 700; color: #F5A623;">
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

                    {{-- Items Table --}}
                    <tr>
                        <td style="padding: 16px 40px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
                                {{-- Table Header --}}
                                <tr>
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
                                        Subtotal
                                    </td>
                                    <td style="padding: 8px 12px; font-size: 14px; color: #e8e8e8; text-align: right; width: 100px;">
                                        {{ number_format($order->subtotal, 2) }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; font-size: 14px; color: #999999; text-align: right;">
                                        Shipping
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
                                    <td style="padding: 12px 12px 8px 12px; font-size: 18px; font-weight: 700; color: #F5A623; text-align: right; border-top: 2px solid #333333;">
                                        Total
                                    </td>
                                    <td style="padding: 12px 12px 8px 12px; font-size: 18px; font-weight: 700; color: #F5A623; text-align: right; width: 100px; border-top: 2px solid #333333;">
                                        {{ number_format($order->total, 2) }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Shipping Address --}}
                    <tr>
                        <td style="padding: 16px 40px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #222222; border-radius: 6px;">
                                <tr>
                                    <td style="padding: 20px 24px;">
                                        <p style="margin: 0 0 8px 0; font-size: 12px; font-weight: 700; color: #999999; text-transform: uppercase; letter-spacing: 1px;">
                                            Shipping Address
                                        </p>
                                        <p style="margin: 0; font-size: 14px; color: #e8e8e8; line-height: 1.6;">
                                            {{ $order->shipping_first_name }} {{ $order->shipping_last_name }}<br>
                                            {{ $order->shipping_address_1 }}<br>
                                            @if ($order->shipping_barangay){{ $order->shipping_barangay }}<br>@endif
                                            @if ($order->shipping_district){{ $order->shipping_district }}, @endif
                                            {{ $order->shipping_city }}
                                            @if ($order->shipping_state), {{ $order->shipping_state }}@endif
                                            @if ($order->shipping_postcode) {{ $order->shipping_postcode }}@endif
                                            <br>{{ $order->shipping_country_code }}
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Payment Method --}}
                    <tr>
                        <td style="padding: 8px 40px 16px 40px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #222222; border-radius: 6px;">
                                <tr>
                                    <td style="padding: 20px 24px;">
                                        <p style="margin: 0 0 8px 0; font-size: 12px; font-weight: 700; color: #999999; text-transform: uppercase; letter-spacing: 1px;">
                                            Payment Method
                                        </p>
                                        <p style="margin: 0; font-size: 14px; color: #e8e8e8;">
                                            @if (str_starts_with($order->payment_method, 'bank_transfer'))
                                                @php
                                                    $bankAcct = null;
                                                    if (preg_match('/^bank_transfer_(\d+)$/', $order->payment_method, $m)) {
                                                        $bankAcct = \App\Models\BankAccount::find((int) $m[1]);
                                                    }
                                                @endphp
                                                {{ $bankAcct ? 'Bank Transfer — ' . $bankAcct->bank_name : 'Bank Transfer' }}
                                            @elseif ($order->payment_method === 'cod')
                                                Cash on Delivery
                                            @else
                                                {{ ucwords(str_replace('_', ' ', $order->payment_method)) }}
                                            @endif
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Bank Transfer Instructions --}}
                    @if ($bankInstructions)
                    <tr>
                        <td style="padding: 0 40px 16px 40px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #2a2211; border: 1px solid #F5A623; border-radius: 6px;">
                                <tr>
                                    <td style="padding: 20px 24px;">
                                        <p style="margin: 0 0 8px 0; font-size: 12px; font-weight: 700; color: #F5A623; text-transform: uppercase; letter-spacing: 1px;">
                                            Bank Transfer Instructions
                                        </p>
                                        <div style="margin: 0; font-size: 14px; color: #e8e8e8; line-height: 1.6;">
                                            {!! nl2br(e($bankInstructions)) !!}
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    @endif

                    {{-- Footer --}}
                    <tr>
                        <td style="padding: 24px 40px 32px 40px; border-top: 1px solid #333333; text-align: center;">
                            <p style="margin: 0 0 8px 0; font-size: 16px; font-weight: 700; color: #F5A623;">
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
