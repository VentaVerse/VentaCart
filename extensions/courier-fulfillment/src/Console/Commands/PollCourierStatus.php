<?php

namespace Extensions\CourierFulfillment\Console\Commands;

use App\Models\Order;
use Extensions\CourierFulfillment\CourierManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Pull/fetch status updates for in-flight orders from each ENABLED courier.
 *
 * "In-flight" = booked with this courier (has a tracking number) and not yet in
 * a terminal state. We only call the courier API for those, so call volume stays
 * proportional to active parcels, not total orders. Runs on a ~10-min schedule
 * (registered in CourierFulfillmentServiceProvider) and is also the same logic
 * the per-order Refresh button uses.
 */
class PollCourierStatus extends Command
{
    protected $signature = 'courier:poll-status';
    protected $description = 'Fetch latest courier status for in-flight (booked, non-terminal) orders from each enabled courier';

    public function handle(): int
    {
        $couriers = CourierManager::enabled();
        if (empty($couriers)) {
            $this->info('No enabled couriers, nothing to poll.');
            return self::SUCCESS;
        }

        [$pendingChecked, $pendingResolved] = $this->resolvePendingCreations();

        $checked = 0;
        $updated = 0;
        $failed = 0;

        foreach ($couriers as $courier) {
            $terminal = $courier->terminalStatuses();

            $orders = Order::query()
                ->where('courier_provider', $courier->getKey())
                ->whereNotNull('courier_tracking_number')
                ->where(function ($q) use ($terminal) {
                    // Poll orders with no status yet OR a non-terminal status.
                    $q->whereNull('courier_status');
                    if (!empty($terminal)) {
                        $q->orWhereNotIn('courier_status', $terminal);
                    }
                })
                ->get();

            foreach ($orders as $order) {
                $checked++;
                $old = $order->courier_status;

                try {
                    $result = $courier->getTracking($order->courier_tracking_number);
                } catch (\Throwable $e) {
                    $failed++;
                    Log::warning('courier:poll-status getTracking threw', [
                        'order' => $order->id, 'courier' => $courier->getKey(), 'error' => $e->getMessage(),
                    ]);
                    continue;
                }

                if (!($result['success'] ?? false)) {
                    $failed++;
                    Log::info('courier:poll-status tracking failed', [
                        'order' => $order->id, 'courier' => $courier->getKey(), 'error' => $result['error'] ?? 'unknown',
                    ]);
                    continue;
                }

                $status = $result['status'] ?? null;
                $statusChanged = $status && $status !== $old;

                // Tracking links / timeline can appear (or change) without a status
                // transition, so check them independently and only write if something
                // actually differs — keeps this a no-op write on unchanged polls.
                $links = $result['links'] ?? null;
                $timeline = $result['timeline'] ?? null;
                $meta = $order->courier_meta ? (json_decode($order->courier_meta, true) ?: []) : [];
                $metaChanged = false;
                if (!empty($links) && ($meta['tracking_links'] ?? null) !== $links) {
                    $meta['tracking_links'] = $links;
                    $metaChanged = true;
                }
                if (!empty($timeline) && ($meta['timeline'] ?? null) !== $timeline) {
                    $meta['timeline'] = $timeline;
                    $metaChanged = true;
                }
                // Task T2 fix (review IMPORTANT 4): SPX's getTracking() also
                // fetches the actual post-booking fee (2.2 batch_get_asf) on
                // every call — this scheduled loop is the ONE hook that
                // actually persists it (was previously fetched every poll
                // and silently discarded by every caller, doubling outbound
                // HTTP for data nobody stored). A courier that doesn't
                // support this (fee_info absent/null) leaves meta untouched.
                $feeInfo = $result['fee_info'] ?? null;
                if (!empty($feeInfo) && ($meta['actual_fee'] ?? null) !== $feeInfo) {
                    $meta['actual_fee'] = $feeInfo;
                    $metaChanged = true;
                }

                // Core tracking_link snapshot: prefer the delivery URL, then pickup.
                // When this poll cycle returned no usable link, keep whatever core
                // already has — never null out a previously stored link.
                // One resolver with refreshTracking() and book(): courier URL,
                // then its public tracker, then what core already holds. Raw
                // column, NOT the accessor, which live-derives.
                $trackingLink = \Extensions\CourierFulfillment\FulfillmentService::resolveTrackingLink($courier, is_array($links) ? $links : null, $order->courier_tracking_number, $order->getRawOriginal('tracking_link'), is_array($meta) ? $meta : []);
                $linkChanged = $trackingLink !== $order->getRawOriginal('tracking_link');
                $nameMissing = $order->shipping_courier_name === null && $order->shipping_courier_id === null;

                if ($statusChanged || $metaChanged || $linkChanged || $nameMissing) {
                    $fill = [];
                    if ($nameMissing) {
                        // Pre-stamp bookings: the shopper saw a bare number.
                        $fill['shipping_courier_name'] = $courier->getName();
                    }
                    if ($statusChanged) {
                        $fill['courier_status'] = $status;
                    }
                    if ($metaChanged) {
                        $fill['courier_meta'] = json_encode($meta);
                    }
                    if ($linkChanged) {
                        $fill['tracking_link'] = $trackingLink;
                    }
                    // courier_* columns aren't in core Order::$fillable — must forceFill.
                    // tracking_link IS fillable, but forceFill here keeps this a single call.
                    $order->forceFill($fill)->save();
                }

                // The map is consulted on EVERY poll, not only when the courier
                // status changed. A mapping the merchant saved AFTER a parcel
                // reached a status must still move the order: one sat on
                // received_at_pickup_hub for days because the status never
                // changed again, so the new mapping was never read. apply() is
                // idempotent and returns false when the order is already there.
                $moved = $status ? \Extensions\CourierFulfillment\StatusMapper::apply($order, $status) : false;
                if ($statusChanged || $moved) {
                    $updated++;
                    $this->line("  order {$order->id}: " . ($old ?? '(none)') . " -> {$status}" . ($moved ? ' (order status moved)' : ''));
                }
            }
        }

        $summary = "Polled {$checked} in-flight order(s): {$updated} updated, {$failed} failed.";
        if ($pendingChecked > 0) {
            $summary = "Resolved {$pendingResolved}/{$pendingChecked} pending async creation(s). " . $summary;
        }
        $this->info($summary);

        return self::SUCCESS;
    }

    /**
     * Task T2 (fulfillment-workflow-v2 + SPX plan): resolves orders stuck in
     * courier_status='creating' — a booking whose courier's own create call
     * is a submit-then-poll async flow (SPX's batch_create_order) that
     * didn't finish inside createShipment()'s own bounded poll window. Runs
     * BEFORE the main getTracking() loop below so a creation resolved in
     * this same command run (now has a tracking number, courier_status still
     * null) gets its first real status in the SAME cycle rather than waiting
     * for the next scheduled run.
     *
     * Every registered courier is checked here (not just enabled() ones) —
     * an admin who disables a courier mid-flight should still see its
     * already-submitted creations resolve rather than getting stuck in
     * 'creating' forever. This is safe for every courier: QuadX/Fake's
     * resolvePendingCreation() is a permanent no-op (their createShipment()
     * is synchronous and never sets courier_status='creating' in the first
     * place), so the query below simply finds nothing for them.
     *
     * @return array{0:int,1:int} [checked, resolved]
     */
    private function resolvePendingCreations(): array
    {
        $checked = 0;
        $resolved = 0;

        foreach (CourierManager::all() as $courier) {
            $pending = Order::query()
                ->where('courier_provider', $courier->getKey())
                ->where('courier_status', 'creating')
                ->whereNull('courier_tracking_number')
                ->get();

            foreach ($pending as $order) {
                $checked++;

                try {
                    $trackingNumber = $courier->resolvePendingCreation($order);
                } catch (\Throwable $e) {
                    Log::warning('courier:poll-status resolvePendingCreation threw', [
                        'order' => $order->id, 'courier' => $courier->getKey(), 'error' => $e->getMessage(),
                    ]);
                    continue;
                }

                if ($trackingNumber === null) {
                    // Still pending, or resolvePendingCreation() already
                    // recorded a terminal failure itself (see its docblock).
                    continue;
                }

                // Complete the booking state exactly as createShipment()'s
                // sync path would via FulfillmentService::book()'s forceFill
                // — same columns, same "courier_meta is a raw JSON string,
                // not array-cast" convention.
                $meta = $order->courier_meta ? (json_decode($order->courier_meta, true) ?: []) : [];
                $meta['pending'] = false;
                $order->forceFill([
                    'courier_tracking_number' => $trackingNumber,
                    // Unknown yet — the main loop below (same run) or the
                    // next scheduled run fetches the real status via
                    // getTracking().
                    'courier_status'          => null,
                    'courier_meta'            => json_encode($meta),
                    'tracking_number'         => $trackingNumber,
                ])->save();

                // fulfillment-placement Task 3: the async half of "booking
                // sets the core status". The shipment only becomes real
                // here, so this is where an async booking earns its move to
                // a To-Handover-placed status - the same shared helper the
                // synchronous FulfillmentService::book() calls, idempotent
                // so a repeat cycle never writes a second history row.
                \Extensions\CourierFulfillment\FulfillmentService::applyBookedStatus(
                    $order,
                    $courier->getName(),
                    $trackingNumber,
                );

                $resolved++;
                $this->line("  order {$order->id}: pending {$courier->getName()} creation resolved -> tracking {$trackingNumber}");
            }
        }

        return [$checked, $resolved];
    }
}
