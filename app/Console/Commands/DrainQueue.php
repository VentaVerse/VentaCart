<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\Factory as QueueFactory;

/**
 * Empty the queue on a host where the daemon worker cannot run.
 *
 * queue:work --stop-when-empty enters Worker::daemon(), which hooks signals
 * through pcntl_signal(). Laravel guards that with extension_loaded('pcntl'),
 * but managed hosts commonly load the extension and then name every pcntl
 * function in disable_functions: the guard passes, the call is undefined, and
 * the worker dies before a single job runs. Queued mail and texts then wait
 * forever with nothing saying why.
 *
 * Worker::runNextJob(), which queue:work --once uses, touches no signals. So
 * this drains by asking for one job at a time until the queue is empty or the
 * time is up, which is what the scheduler wants from a per-minute run anyway.
 */
class DrainQueue extends Command
{
    protected $signature = 'queue:drain
                            {--queue= : The queue to drain, or the connection default}
                            {--max-time=50 : Seconds to keep working before stopping}';

    protected $description = 'Process queued jobs until the queue is empty, for hosts where queue:work cannot run';

    public function handle(QueueFactory $queues): int
    {
        $deadline = microtime(true) + (float) $this->option('max-time');
        $queue = $this->option('queue') ?: null;
        $sent = 0;

        while (microtime(true) < $deadline) {
            $waiting = $queues->connection()->size($queue);

            if ($waiting === 0) {
                break;
            }

            $this->call('queue:work', [
                '--once' => true,
                '--sleep' => 0,
                ...($queue === null ? [] : ['--queue' => $queue]),
            ]);

            // A queue that did not shrink is holding jobs that are not due:
            // a released retry waiting out its backoff. Leave them for the
            // next run rather than spinning against the deadline.
            if ($queues->connection()->size($queue) >= $waiting) {
                break;
            }

            $sent++;
        }

        $this->info("queue:drain processed {$sent} job(s).");

        return self::SUCCESS;
    }
}
