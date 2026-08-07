<?php

namespace Extensions\CourierFulfillment\Database\Seeders;

use App\Models\Automation;
use App\Services\CronExpressionBuilder;
use Illuminate\Database\Seeder;

/**
 * Registers the courier status-poll as a managed automation so it appears in
 * Settings > Automations and is driven by the global schedule:run cron — rather
 * than a schedule hardcoded in the service provider.
 *
 * firstOrCreate (like core's AutomationSeeder): once the row exists the admin
 * owns its schedule/enabled flag; re-seeding never overwrites their edits.
 */
class CourierFulfillmentAutomationSeeder extends Seeder
{
    public function run(): void
    {
        $preset = 'every_n_minutes';
        $params = ['minute' => 10];

        Automation::firstOrCreate(
            ['key' => 'courier_poll_status'],
            [
                'name' => 'Courier Status Polling',
                'command' => 'courier:poll-status',
                'description' => 'Fetches the latest courier status for in-flight (booked, non-terminal) orders from each enabled courier.',
                'schedule_preset' => $preset,
                'schedule_params' => $params,
                'cron_expression' => CronExpressionBuilder::build($preset, $params),
                'enabled' => true,
            ]
        );
    }
}
