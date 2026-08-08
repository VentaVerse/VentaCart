<?php

namespace Extensions\CourierFulfillment\Jobs;

use Extensions\CourierFulfillment\Support\CourierWebhookIngestor;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Task T3 (fulfillment-workflow-v2 + SPX plan): the deferred half of a
 * fast-ack courier webhook (SPX today) — see
 * CourierWebhookController::handle()'s SPX branch.
 *
 * Deliberately NOT `implements ShouldQueue`. This job only ever runs via
 * `dispatch($job)->afterResponse()`, never a plain `dispatch($job)` — see
 * that call site's docblock for why `->afterResponse()` (not this app's
 * configured QUEUE_CONNECTION, which is `database` and needs a running
 * `queue:work` worker — see docker/supervisord.conf) is the mechanism that
 * actually satisfies SPX's 3-second webhook-ack budget. Marking this
 * ShouldQueue would invite a future `dispatch($job)` (no `->afterResponse()`)
 * to silently fall back to sitting in the `jobs` table instead of running
 * deterministically after the response — a worker-liveness dependency this
 * class is specifically here to avoid. `handle()` is plain and callable
 * directly, so tests can also invoke it synchronously without any queue
 * infrastructure at all.
 */
class ProcessCourierWebhookJob
{
    use Dispatchable;

    /** @param array<int, array{reference_id:?string, tracking_number:?string, status:string, occurred_at:?string, remarks:?string}> $events */
    public function __construct(
        public readonly string $provider,
        public readonly array $events,
    ) {
    }

    public function handle(): void
    {
        CourierWebhookIngestor::ingest($this->provider, $this->events);
    }
}
