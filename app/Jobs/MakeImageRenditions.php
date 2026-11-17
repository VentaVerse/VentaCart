<?php

namespace App\Jobs;

use App\Support\ImageRenditions;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Make the smaller copies of one stored photo (see ImageRenditions).
 *
 * Queued so neither an upload nor the page that first meets a photo waits
 * for the resizing. One try: a photo that cannot be decoded will not decode
 * on a second attempt, and the page keeps showing the original meanwhile.
 */
class MakeImageRenditions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public readonly string $path)
    {
    }

    public function handle(): void
    {
        ImageRenditions::make($this->path);
    }
}
