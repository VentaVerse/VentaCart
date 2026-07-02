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
            $this->info('No enabled couriers — nothing to poll.');
            return self::SUCCESS;
        }

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

                // Core tracking_link snapshot: prefer the delivery URL, then pickup.
                // When this poll cycle returned no usable link, keep whatever core
                // already has — never null out a previously stored link.
                $trackingLink = null;
                foreach ([$links['delivery'] ?? null, $links['pickup'] ?? null] as $u) {
                    if (is_string($u) && str_starts_with($u, 'http')) {
                        $trackingLink = $u;
                        break;
                    }
                }
                $trackingLink = $trackingLink ?? $order->tracking_link;
                $linkChanged = $trackingLink !== $order->tracking_link;

                if ($statusChanged || $metaChanged || $linkChanged) {
                    $fill = [];
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
                    if ($statusChanged) {
                        \Extensions\CourierFulfillment\StatusMapper::apply($order, $status);
                        $updated++;
                        $this->line("  order {$order->id}: " . ($old ?? '(none)') . " -> {$status}");
                    }
                }
            }
        }

        $this->info("Polled {$checked} in-flight order(s): {$updated} updated, {$failed} failed.");

        return self::SUCCESS;
    }
}
