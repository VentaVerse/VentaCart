<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'store_id' => 1,
                'slug' => 'order_confirmation',
                'name' => 'Order Confirmation',
                'subject' => 'Order #{order_id} Confirmed — {store_name}',
                'type' => 'customer',
                'body' => '<p>Hi {customer_name},</p>
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
<p>Thank you,<br>{store_name}</p>',
            ],
            [
                'store_id' => 1,
                'slug' => 'order_status_update',
                'name' => 'Order Status Update',
                'subject' => 'Order #{order_id} Update — {new_status}',
                'type' => 'customer',
                'body' => '<p>Hi {customer_name},</p>
<p>Your order <strong style="color: #F5A623;">#{order_id}</strong> has been updated.</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 20px 0;">
<tr>
<td style="padding: 16px; background-color: #222222; border-radius: 6px;">
<p style="margin: 0 0 4px 0; font-size: 12px; color: #999999; text-transform: uppercase; letter-spacing: 1px;">Status Change</p>
<p style="margin: 0; font-size: 15px; color: #e8e8e8;"><span style="color: #999999;">{old_status}</span> &rarr; <span style="color: #F5A623; font-weight: 700;">{new_status}</span></p>
</td>
</tr>
</table>
<p>{comment}</p>
<p>Thank you,<br>{store_name}</p>',
            ],
            [
                'store_id' => 1,
                'slug' => 'shipping_notification',
                'name' => 'Shipping Notification',
                'subject' => 'Your order #{order_id} has shipped!',
                'type' => 'customer',
                'body' => '<p>Hi {customer_name},</p>
<p>Great news! Your order <strong style="color: #F5A623;">#{order_id}</strong> has been shipped.</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 20px 0;">
<tr>
<td style="padding: 16px; background-color: #222222; border-radius: 6px;">
<p style="margin: 0 0 4px 0; font-size: 12px; color: #999999; text-transform: uppercase; letter-spacing: 1px;">Tracking Information</p>
<p style="margin: 0 0 4px 0; font-size: 14px; color: #e8e8e8;"><strong>Carrier:</strong> {carrier_name}</p>
<p style="margin: 0; font-size: 14px; color: #e8e8e8;"><strong>Tracking Number:</strong> <span style="color: #F5A623;">{tracking_number}</span></p>
</td>
</tr>
</table>
<p>Thank you for shopping with us!</p>
<p>Thank you,<br>{store_name}</p>',
            ],
            [
                'store_id' => 1,
                'slug' => 'welcome',
                'name' => 'Welcome Email',
                'subject' => 'Welcome to {store_name}!',
                'type' => 'customer',
                'body' => '<p>Hi {customer_name},</p>
<p>Welcome to <strong style="color: #F5A623;">{store_name}</strong>! We\'re thrilled to have you on board.</p>
<p>Browse our latest gear, discover new products, and find the perfect additions to your setup.</p>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin: 24px 0;">
<tr>
<td style="background-color: #F5A623; border-radius: 6px; padding: 12px 28px;">
<a href="{store_url}" style="color: #111111; font-size: 15px; font-weight: 700; text-decoration: none; display: inline-block;">Shop Now</a>
</td>
</tr>
</table>
<p>Thank you,<br>{store_name}</p>',
            ],
            [
                'store_id' => 1,
                'slug' => 'password_reset',
                'name' => 'Password Reset',
                'subject' => 'Reset Your Password — {store_name}',
                'type' => 'customer',
                'body' => '<p>Hi {customer_name},</p>
<p>We received a request to reset your password. Click the button below to set a new password:</p>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin: 24px 0;">
<tr>
<td style="background-color: #F5A623; border-radius: 6px; padding: 12px 28px;">
<a href="{reset_url}" style="color: #111111; font-size: 15px; font-weight: 700; text-decoration: none; display: inline-block;">Reset Password</a>
</td>
</tr>
</table>
<p style="font-size: 13px; color: #999999;">If you didn\'t request this, you can safely ignore this email. This link will expire in 60 minutes.</p>
<p>Thank you,<br>{store_name}</p>',
            ],
            [
                'store_id' => 1,
                'slug' => 'review_request',
                'name' => 'Review Request',
                'subject' => 'How was your {product_name}?',
                'type' => 'customer',
                'body' => '<p>Hi {customer_name},</p>
<p>We hope you\'re enjoying your <strong style="color: #F5A623;">{product_name}</strong>!</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 20px 0;">
<tr>
<td style="padding: 20px; background-color: #222222; border-radius: 6px; text-align: center;">
<p style="margin: 0 0 12px 0; font-size: 28px;">&#11088;&#11088;&#11088;&#11088;&#11088;</p>
<p style="margin: 0 0 16px 0; font-size: 15px; color: #e8e8e8;">How would you rate your purchase?</p>
<a href="{review_url}" style="display: inline-block; background-color: #F5A623; color: #111111; font-size: 15px; font-weight: 700; text-decoration: none; padding: 12px 28px; border-radius: 6px;">Write a Review</a>
</td>
</tr>
</table>
<p>Your feedback helps other musicians make informed decisions.</p>
<p>Thank you,<br>{store_name}</p>',
            ],
            [
                'store_id' => 1,
                'slug' => 'new_order_admin',
                'name' => 'New Order (Admin)',
                'subject' => 'New Order #{order_id} — {customer_name}',
                'type' => 'admin',
                'body' => '<p>A new order has been placed.</p>
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
</table>',
            ],
            [
                'store_id' => 1,
                'slug' => 'low_stock_admin',
                'name' => 'Low Stock Alert (Admin)',
                'subject' => 'Low Stock Alert: {product_name}',
                'type' => 'admin',
                'body' => '<p>A product has fallen below the low stock threshold.</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 20px 0;">
<tr>
<td style="padding: 16px; background-color: #222222; border-radius: 6px;">
<p style="margin: 0 0 8px 0; font-size: 12px; color: #999999; text-transform: uppercase; letter-spacing: 1px;">Stock Alert</p>
<p style="margin: 0 0 4px 0; font-size: 14px; color: #e8e8e8;"><strong>Product:</strong> <span style="color: #F5A623;">{product_name}</span></p>
<p style="margin: 0 0 4px 0; font-size: 14px; color: #e8e8e8;"><strong>SKU:</strong> {product_sku}</p>
<p style="margin: 0; font-size: 14px; color: #e8e8e8;"><strong>Current Quantity:</strong> <span style="color: #E2293D; font-weight: 700;">{current_qty}</span></p>
</td>
</tr>
</table>
<p>Please restock this product as soon as possible.</p>
<p>{store_name}</p>',
            ],
            [
                'store_id' => 1,
                'slug' => 'account_created_by_admin',
                'name' => 'Account Created by Admin',
                'subject' => 'Your account at {store_name}',
                'type' => 'customer',
                'body' => '<p>Hi {customer_name},</p>
<p>An account has been created for you at <strong>{store_name}</strong>.</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 20px 0;">
<tr>
<td style="padding: 16px; background-color: #222222; border-radius: 6px;">
<p style="margin: 0 0 8px 0; font-size: 12px; color: #999999; text-transform: uppercase; letter-spacing: 1px;">Your Login Details</p>
<p style="margin: 0 0 4px 0; font-size: 14px; color: #e8e8e8;"><strong>Email:</strong> {email}</p>
<p style="margin: 0; font-size: 14px; color: #e8e8e8;"><strong>Password:</strong> <span style="font-family: monospace; background: #333; padding: 2px 8px; border-radius: 3px;">{password}</span></p>
</td>
</tr>
</table>
<p>Please change your password after your first login.</p>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin: 24px 0;">
<tr>
<td style="background-color: #F5A623; border-radius: 6px; padding: 12px 28px;">
<a href="{login_url}" style="color: #111111; font-size: 15px; font-weight: 700; text-decoration: none; display: inline-block;">Log In to Your Account</a>
</td>
</tr>
</table>
<p>Thank you,<br>{store_name}</p>',
            ],
        ];

        foreach ($templates as $template) {
            EmailTemplate::updateOrCreate(
                ['slug' => $template['slug']],
                $template
            );
        }
    }
}
