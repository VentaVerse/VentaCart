<?php

namespace Extensions\CourierFulfillment\Support;

use App\Models\Order;
use Extensions\CourierFulfillment\StatusMapper;
use Illuminate\Support\Facades\Log;

/**
 * Task T3 (fulfillment-workflow-v2 + SPX plan): the actual order-matching +
 * status-mapping work every courier webhook path needs, extracted out of
 * CourierWebhookController so it can be called from TWO places with
 * byte-identical behaviour:
 *
 *  - Synchronously, inline, for every courier whose webhook contract has no
 *    fast-ack requirement (QuadX today) — CourierWebhookController::handle()
 *    calls ingest() directly, exactly where the old inline `foreach` +
 *    private applyEvent() used to live. QuadX's own webhook tests are the
 *    contract for "byte-identical" here: same order lookup, same
 *    StatusMapper::apply() call, same log line on a miss.
 *  - From inside Extensions\CourierFulfillment\Jobs\ProcessCourierWebhookJob,
 *    for a courier whose docs impose a hard response-time budget (SPX: SPX
 *    stops sending webhooks entirely after repeated timeouts/rejections —
 *    api.md §4.1) — the controller acks 200 first, THIS same method runs
 *    afterward via dispatch()->afterResponse().
 *
 * F2 fix (this task's brief, item 3): the tracking-number fallback lookup
 * (used when an event carries no usable reference_id) now scopes to
 * `courier_provider`. Before this fix a tracking-number collision between
 * two different couriers could apply one courier's webhook event to the
 * OTHER courier's order — harmless with a single courier registered, a real
 * cross-provider data-integrity risk the moment a second one is. See this
 * extension's README ("Add a courier" checklist) for the caveat this closes.
 */
class CourierWebhookIngestor
{
    /** @param array<int, array{reference_id:?string, tracking_number:?string, status:string, occurred_at:?string, remarks:?string}> $events */
    public static function ingest(string $provider, array $events): void
    {
        foreach ($events as $event) {
            self::applyEvent($provider, $event);
        }
    }

    private static function applyEvent(string $provider, array $event): void
    {
        // Match by reference_id (order id we sent at booking); fall back to
        // tracking number, scoped to THIS provider (F2 fix — see class docblock).
        $order = null;
        if (!empty($event['reference_id'])) {
            $order = Order::find($event['reference_id']);
        }
        if (!$order && !empty($event['tracking_number'])) {
            $order = Order::where('courier_tracking_number', $event['tracking_number'])
                ->where('courier_provider', $provider)
                ->first();
        }

        if (!$order) {
            Log::info('Courier webhook: no matching order', ['provider' => $provider, 'event' => $event]);
            return;
        }

        $order->forceFill(['courier_status' => $event['status']])->save();
        StatusMapper::apply($order, $event['status'] ?? null);
    }
}
