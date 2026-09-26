<?php

namespace Tests\Support;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * A job that only records that it ran, so a drain can be proved by what
 * came off the queue rather than by mocking the worker.
 */
class RecordingQueueJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use SerializesModels;

    /** @var list<string> */
    public static array $ran = [];

    public function __construct(private string $mark)
    {
    }

    public function handle(): void
    {
        self::$ran[] = $this->mark;
    }
}
