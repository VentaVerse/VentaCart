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
 *
 * Final-review fix (Finding 3): the PRIMARY reference_id match is now
 * verified against the order's CURRENT shipment (provider must match; and
 * tracking numbers must match when both sides carry one) before any write —
 * see applyEvent()'s inline comment for the superseded-shipment hazard this
 * closes.
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

            // Final-review fix (Finding 3): a reference_id hit alone is
            // neither provider-scoped nor tracking-verified — reference_id
            // is just the order id, and it's REUSED across shipments (a
            // cancel -> rebook books the same reference_id again, possibly
            // with a different courier). A late webhook from a superseded
            // shipment must not overwrite the CURRENT booking's status, so
            // the event has to prove it belongs to the order's current
            // shipment: the order's provider must be this webhook's
            // provider, AND — when both the order and the event carry a
            // tracking number — those must match too (events without a
            // tracking number, or orders still mid-create without one, are
            // exempt from the tracking check only, never the provider one).
            // On mismatch: log + ignore, no write, and no fallback lookup —
            // a stale event must not go shopping for another order to hit.
            if ($order) {
                if ((string) $order->courier_provider !== $provider) {
                    Log::info('Courier webhook: event provider does not match the order\'s current booking — ignored', [
                        'provider' => $provider, 'order' => $order->id,
                        'order_provider' => $order->courier_provider, 'event' => $event,
                    ]);
                    return;
                }

                $orderTracking = (string) $order->courier_tracking_number;
                $eventTracking = (string) ($event['tracking_number'] ?? '');
                if ($orderTracking !== '' && $eventTracking !== '' && $orderTracking !== $eventTracking) {
                    Log::info('Courier webhook: event tracking number does not match the order\'s current shipment — ignored (late event from a superseded booking?)', [
                        'provider' => $provider, 'order' => $order->id,
                        'order_tracking' => $orderTracking, 'event' => $event,
                    ]);
                    return;
                }
            }
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
