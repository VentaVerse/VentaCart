<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        try {
            DB::table('email_templates')->limit(1)->first();
        } catch (\Exception $e) {
            return;
        }

        $orderConfirmationBody = '<p>Hi {customer_name},</p>
<p>Thank you for your order! We\'re happy to confirm that your order <strong style="color: #F5A623;">#{order_id}</strong> has been received and is being processed.</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 16px 0;">
<tr><td style="padding: 16px 20px; background-color: #222222; border-radius: 6px;">
<p style="margin: 0 0 6px 0; font-size: 11px; font-weight: 700; color: #999999; text-transform: uppercase; letter-spacing: 1px;">Shipping Address</p>
<p style="margin: 0; font-size: 14px; color: #e8e8e8; line-height: 1.6;">{shipping_address}</p>
</td></tr>
</table>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 16px 0;">
<tr>
<td style="width: 50%; padding: 16px 20px; background-color: #222222; border-radius: 6px 0 0 6px; vertical-align: top;">
<p style="margin: 0 0 4px 0; font-size: 11px; font-weight: 700; color: #999999; text-transform: uppercase; letter-spacing: 1px;">Shipping Method</p>
<p style="margin: 0; font-size: 14px; color: #e8e8e8;">{shipping_method} &mdash; {shipping_cost}</p>
</td>
<td style="width: 50%; padding: 16px 20px; background-color: #222222; border-radius: 0 6px 6px 0; vertical-align: top;">
<p style="margin: 0 0 4px 0; font-size: 11px; font-weight: 700; color: #999999; text-transform: uppercase; letter-spacing: 1px;">Payment Method</p>
<p style="margin: 0; font-size: 14px; color: #e8e8e8;">{payment_method}</p>
</td>
</tr>
</table>
{comment}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 16px 0; border-collapse: collapse;">
<tr><td style="padding: 12px 16px; background-color: #222222; border-radius: 6px;">
<p style="margin: 0 0 10px 0; font-size: 11px; font-weight: 700; color: #999999; text-transform: uppercase; letter-spacing: 1px;">Order Summary</p>
{order_items}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top: 12px; border-top: 1px solid #333333;">
<tr><td style="padding: 8px 0 2px 0; font-size: 13px; color: #999;">Subtotal</td><td style="padding: 8px 0 2px 0; font-size: 13px; color: #e8e8e8; text-align: right;">{subtotal}</td></tr>
<tr><td style="padding: 2px 0 8px 0; font-size: 13px; color: #999;">Shipping ({shipping_method})</td><td style="padding: 2px 0 8px 0; font-size: 13px; color: #e8e8e8; text-align: right;">{shipping_cost}</td></tr>
<tr><td style="padding: 10px 0 0 0; font-size: 16px; font-weight: 700; color: #F5A623; border-top: 1px solid #333333;">Total</td><td style="padding: 10px 0 0 0; font-size: 16px; font-weight: 700; color: #F5A623; text-align: right; border-top: 1px solid #333333;">{order_total}</td></tr>
</table>
</td></tr>
</table>
<p>We\'ll send you another email when your order ships.</p>
<p>Thank you,<br>{store_name}</p>';

        $adminBody = '<p>A new order has been placed.</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 16px 0;">
<tr><td style="padding: 16px 20px; background-color: #222222; border-radius: 6px;">
<p style="margin: 0 0 8px 0; font-size: 11px; font-weight: 700; color: #999999; text-transform: uppercase; letter-spacing: 1px;">Order Details</p>
<p style="margin: 0 0 4px 0; font-size: 14px; color: #e8e8e8;"><strong>Order ID:</strong> <span style="color: #F5A623;">#{order_id}</span></p>
<p style="margin: 0 0 4px 0; font-size: 14px; color: #e8e8e8;"><strong>Customer:</strong> {customer_name}</p>
<p style="margin: 0 0 4px 0; font-size: 14px; color: #e8e8e8;"><strong>Email:</strong> {customer_email}</p>
<p style="margin: 0 0 4px 0; font-size: 14px; color: #e8e8e8;"><strong>Payment:</strong> {payment_method}</p>
</td></tr>
</table>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 16px 0;">
<tr><td style="padding: 16px 20px; background-color: #222222; border-radius: 6px;">
<p style="margin: 0 0 6px 0; font-size: 11px; font-weight: 700; color: #999999; text-transform: uppercase; letter-spacing: 1px;">Shipping Address</p>
<p style="margin: 0; font-size: 14px; color: #e8e8e8; line-height: 1.6;">{shipping_address}</p>
</td></tr>
</table>
{comment}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 16px 0; border-collapse: collapse;">
<tr><td style="padding: 12px 16px; background-color: #222222; border-radius: 6px;">
<p style="margin: 0 0 10px 0; font-size: 11px; font-weight: 700; color: #999999; text-transform: uppercase; letter-spacing: 1px;">Items</p>
{order_items}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top: 12px; border-top: 1px solid #333333;">
<tr><td style="padding: 8px 0 2px 0; font-size: 13px; color: #999;">Subtotal</td><td style="padding: 8px 0 2px 0; font-size: 13px; color: #e8e8e8; text-align: right;">{subtotal}</td></tr>
<tr><td style="padding: 2px 0 8px 0; font-size: 13px; color: #999;">Shipping ({shipping_method})</td><td style="padding: 2px 0 8px 0; font-size: 13px; color: #e8e8e8; text-align: right;">{shipping_cost}</td></tr>
<tr><td style="padding: 10px 0 0 0; font-size: 16px; font-weight: 700; color: #F5A623; border-top: 1px solid #333333;">Total</td><td style="padding: 10px 0 0 0; font-size: 16px; font-weight: 700; color: #F5A623; text-align: right; border-top: 1px solid #333333;">{order_total}</td></tr>
</table>
</td></tr>
</table>';

        DB::table('email_templates')
            ->where('slug', 'order_confirmation')
            ->update(['body' => $orderConfirmationBody]);

        DB::table('email_templates')
            ->where('slug', 'new_order_admin')
            ->update(['body' => $adminBody]);
    }

    public function down(): void
    {
        // No rollback — templates can be edited via admin
    }
};
