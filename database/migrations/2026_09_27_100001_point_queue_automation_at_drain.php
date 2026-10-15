<?php

use App\Models\Automation;
use Database\Seeders\AutomationSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * The queue automation added yesterday names queue:work, which cannot run on
 * a host that disables pcntl_signal: it dies before a job is taken, so the
 * store still sends nothing. queue:drain does the same work without signals.
 *
 * Only the command this project shipped is replaced. An admin who has since
 * pointed the row somewhere of their own keeps it, because an automation is
 * theirs once installed.
 */
return new class extends Migration
{
    private const SHIPPED = 'queue:work --stop-when-empty --max-time=50';

    public function up(): void
    {
        Automation::where('key', 'queue_work')
            ->where('command', self::SHIPPED)
            ->update(['command' => AutomationSeeder::queueRow()['command']]);
    }

    public function down(): void
    {
        Automation::where('key', 'queue_work')
            ->where('command', AutomationSeeder::queueRow()['command'])
            ->update(['command' => self::SHIPPED]);
    }
};
