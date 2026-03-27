<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class AbandonedCartTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'store_id' => 1,
                'slug' => 'abandoned_cart_reminder',
                'name' => 'Abandoned Cart Reminder',
                'subject' => 'You left something behind, {customer_name}!',
                'type' => 'customer',
                'body' => '<p>Hi {customer_name},</p>
<p>Looks like you left some items in your cart. No worries — they\'re still waiting for you!</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 20px 0; border-collapse: collapse;">
<tr>
<td style="padding: 12px 16px; background-color: #222222; border-radius: 6px;">
<p style="margin: 0 0 8px 0; font-size: 12px; font-weight: 700; color: #999999; text-transform: uppercase; letter-spacing: 1px;">Your Cart</p>
{cart_items}
<p style="margin: 12px 0 0 0; padding-top: 12px; border-top: 1px solid #333333; font-size: 16px; font-weight: 700; color: #F5A623;">Total: {cart_total}</p>
</td>
</tr>
</table>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin: 24px 0;">
<tr>
<td style="background-color: #F5A623; border-radius: 6px; padding: 12px 28px;">
<a href="{cart_url}" style="color: #111111; font-size: 15px; font-weight: 700; text-decoration: none; display: inline-block;">Complete Your Order</a>
</td>
</tr>
</table>
<p>Thank you,<br>{store_name}</p>',
            ],
            [
                'store_id' => 1,
                'slug' => 'abandoned_cart_final',
                'name' => 'Abandoned Cart — Last Chance',
                'subject' => '{customer_name}, your cart is still waiting',
                'type' => 'customer',
                'body' => '<p>Hi {customer_name},</p>
<p>Just a heads up — these items are still in your cart and may sell out soon:</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 20px 0; border-collapse: collapse;">
<tr>
<td style="padding: 12px 16px; background-color: #222222; border-radius: 6px;">
<p style="margin: 0 0 8px 0; font-size: 12px; font-weight: 700; color: #999999; text-transform: uppercase; letter-spacing: 1px;">Your Cart</p>
{cart_items}
<p style="margin: 12px 0 0 0; padding-top: 12px; border-top: 1px solid #333333; font-size: 16px; font-weight: 700; color: #F5A623;">Total: {cart_total}</p>
</td>
</tr>
</table>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin: 24px 0;">
<tr>
<td style="background-color: #F5A623; border-radius: 6px; padding: 12px 28px;">
<a href="{cart_url}" style="color: #111111; font-size: 15px; font-weight: 700; text-decoration: none; display: inline-block;">Complete Your Order</a>
</td>
</tr>
</table>
<p>Don\'t miss out!</p>
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
