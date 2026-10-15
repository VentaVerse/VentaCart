<?php

namespace Tests\Feature\Queue;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\RecordingQueueJob;
use Tests\TestCase;

/**
 * Hosts that harden PHP disable pcntl_signal while leaving the pcntl
 * extension loaded, and Laravel's worker only checks for the extension:
 * queue:work dies on such a host, so queued mail and texts never send.
 * queue:drain processes the same jobs without the daemon's signal handling.
 */
class DrainQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The suite runs on the sync queue, where a dispatch never reaches a
        // table. This command exists to empty a real queue, so these tests
        // need one.
        config(['queue.default' => 'database']);

        RecordingQueueJob::$ran = [];
    }

    public function test_it_runs_every_pending_job_and_leaves_the_queue_empty(): void
    {
        RecordingQueueJob::dispatch('first');
        RecordingQueueJob::dispatch('second');
        $this->assertSame(2, DB::table('jobs')->count(), 'the jobs should be waiting before the drain');

        $this->artisan('queue:drain')->assertExitCode(0);

        $this->assertSame(['first', 'second'], RecordingQueueJob::$ran);
        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_it_leaves_the_queue_alone_when_it_has_no_time_to_work(): void
    {
        RecordingQueueJob::dispatch('first');

        $this->artisan('queue:drain --max-time=0')->assertExitCode(0);

        $this->assertSame([], RecordingQueueJob::$ran);
        $this->assertSame(1, DB::table('jobs')->count());
    }

    public function test_it_succeeds_on_an_empty_queue(): void
    {
        $this->artisan('queue:drain')->assertExitCode(0);

        $this->assertSame([], RecordingQueueJob::$ran);
    }
}
