<?php

namespace Extensions\StockAlerts\Database\Seeders;

use Extensions\StockAlerts\Models\StockNotificationTemplate;
use Illuminate\Database\Seeder;

class StockNotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        StockNotificationTemplate::forStore(1);
    }
}
