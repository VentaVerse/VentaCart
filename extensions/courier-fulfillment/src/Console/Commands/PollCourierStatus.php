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
                if ($status && $status !== $old) {
                    // courier_* columns aren't in core Order::$fillable — must forceFill.
                    $order->forceFill(['courier_status' => $status])->save();
                    $updated++;
                    $this->line("  order {$order->id}: " . ($old ?? '(none)') . " -> {$status}");
                }
            }
        }

        $this->info("Polled {$checked} in-flight order(s): {$updated} updated, {$failed} failed.");

        return self::SUCCESS;
    }
}
