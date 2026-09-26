<?php

use App\Models\Automation;
use App\Services\CronExpressionBuilder;
use Database\Seeders\AutomationSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Stores installed before notifications were queued have no way to send
 * them unless a worker runs. Their automations came from the seeder long
 * ago and are the admin's now, so this adds only the one missing row and
 * leaves every other automation exactly as it is.
 */
return new class extends Migration
{
    public function up(): void
    {
        $row = AutomationSeeder::queueRow();
        $row['cron_expression'] = CronExpressionBuilder::build($row['schedule_preset'], $row['schedule_params']);

        Automation::firstOrCreate(['key' => $row['key']], $row);
    }

    public function down(): void
    {
        Automation::where('key', 'queue_work')->delete();
    }
};
