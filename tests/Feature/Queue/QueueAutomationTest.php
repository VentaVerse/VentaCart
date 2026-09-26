<?php

namespace Tests\Feature\Queue;

use App\Models\Automation;
use Database\Seeders\AutomationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * The automation that sends queued mail and texts on a host with only cron.
 *
 * It has to name a command that runs where it is pointed: queue:work dies on
 * a host that disables pcntl_signal, and the store then sends nothing with
 * nothing to show why.
 */
class QueueAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_queue_automation_drains_rather_than_running_the_daemon_worker(): void
    {
        $this->assertStringStartsWith('queue:drain', AutomationSeeder::queueRow()['command']);
    }

    public function test_the_command_it_names_is_registered(): void
    {
        $command = AutomationSeeder::queueRow()['command'];
        $name = explode(' ', $command)[0];

        $this->assertArrayHasKey($name, Artisan::all(), "the automation names {$name}, which no command provides");
    }

    /** A store that already had the shipped row is moved onto the new command. */
    public function test_the_migration_repoints_a_row_left_on_the_daemon_worker(): void
    {
        Automation::where('key', 'queue_work')->update(['command' => 'queue:work --stop-when-empty --max-time=50']);

        $this->runMigration();

        $this->assertSame(AutomationSeeder::queueRow()['command'], Automation::where('key', 'queue_work')->value('command'));
    }

    /** A command the admin chose themselves is theirs, and stays. */
    public function test_the_migration_leaves_a_command_the_admin_changed(): void
    {
        Automation::where('key', 'queue_work')->update(['command' => 'queue:drain --max-time=20']);

        $this->runMigration();

        $this->assertSame('queue:drain --max-time=20', Automation::where('key', 'queue_work')->value('command'));
    }

    private function runMigration(): void
    {
        (require base_path('database/migrations/2026_09_27_100001_point_queue_automation_at_drain.php'))->up();
    }
}
