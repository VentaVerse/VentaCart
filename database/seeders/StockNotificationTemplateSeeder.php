<?php

namespace Database\Seeders;

use App\Models\StockNotificationTemplate;
use Illuminate\Database\Seeder;

class StockNotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        StockNotificationTemplate::firstOrCreate(
            ['store_id' => 1],
            [
                'subject' => 'Good news! {product_name} is back in stock',
                'body' => '<p>Hi {customer_name},</p>
<p>Great news! The <strong>{product_name}</strong>{variant_name} you were waiting for is now back in stock at {store_name}.</p>
<p><a href="{product_url}">View Product</a></p>
<p>Hurry — stock is limited and this item may sell out again!</p>
<p>Thank you,<br>{store_name}</p>',
            ]
        );
    }
}
