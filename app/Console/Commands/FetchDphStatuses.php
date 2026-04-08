<?php

namespace App\Console\Commands;

use App\Models\DphStatus;
use App\Models\OrderHistory;
use App\Models\Shipment;
use App\Services\DeliveryPhService;
use App\Services\StockService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchDphStatuses extends Command
{
    protected $signature = 'dph:fetch-statuses';
    protected $description = 'Poll DPH API for shipment status updates on active bookings';

    public function handle(): int
    {
        if (!DeliveryPhService::isEnabled()) {
            $this->warn('DPH is not enabled.');
            return 0;
        }

        $shipments = Shipment::whereNotNull('dph_post_id')
            ->whereNotIn('dph_status', ['DONE', 'CANCEL', 'RETURNED'])
            ->with('order')
            ->get();

        if ($shipments->isEmpty()) {
            $this->info('No active shipments to check.');
            return 0;
        }

        $dph = app(DeliveryPhService::class);
        $updated = 0;

        foreach ($shipments as $shipment) {
            try {
                $result = $dph->getPost($shipment->dph_post_id);

                if (empty($result)) {
                    $this->line("  #{$shipment->order_id} ({$shipment->dph_post_id}): empty response — skipped");
                    continue;
                }

                $status = $result['status'] ?? null;
                $subStatus = $result['subStatus'] ?? null;
                $trackingNumber = $result['deliveryDetails']['trackingNumber'] ?? null;
                $trackingUrl = $result['deliveryDetails']['trackingUrl'] ?? null;
                $shareableLink = $result['shareableLink'] ?? null;
                $feeApproximate = $result['approximateShippingFee'] ?? null;
                $feeActual = $result['actualShippingFee'] ?? null;
                $feeFinal = $result['finalShippingFee'] ?? null;
                $courierName = $result['courierName'] ?? null;

                if (!$status || ($shipment->dph_status === $status && $shipment->dph_sub_status === $subStatus)) {
                    $this->line("  #{$shipment->order_id}: no change ({$status})");
                    continue;
                }

                // Log the status change event
                $shipment->events()->create([
                    'status' => $status,
                    'sub_status' => $subStatus,
                    'remarks' => 'Polled via dph:fetch-statuses',
                    'meta' => $result,
                ]);

                $shipmentUpdate = [
                    'dph_status' => $status,
                    'dph_sub_status' => $subStatus,
                ];

                if ($trackingNumber) $shipmentUpdate['tracking_number'] = $trackingNumber;
                if ($trackingUrl) $shipmentUpdate['tracking_url'] = $trackingUrl;
                if ($shareableLink) $shipmentUpdate['shareable_link'] = $shareableLink;
                if ($feeApproximate !== null) $shipmentUpdate['approximate_fee'] = (float) $feeApproximate;
                if ($feeActual !== null) $shipmentUpdate['actual_fee'] = (float) $feeActual;
                if ($feeFinal !== null) $shipmentUpdate['final_fee'] = (float) $feeFinal;

                if ($status === 'DONE') {
                    $shipmentUpdate['delivered_at'] = now();
                }

                $shipment->update($shipmentUpdate);

                // Update order status based on DPH status mapping
                $order = $shipment->order;
                if ($order && $status) {
                    $dphStatus = DphStatus::where('code', $status)->first();

                    if ($dphStatus && $dphStatus->order_status_id && (int) $order->order_status_id !== $dphStatus->order_status_id) {
                        $order->update(['order_status_id' => $dphStatus->order_status_id]);

                        OrderHistory::create([
                            'order_id' => $order->id,
                            'order_status_id' => $dphStatus->order_status_id,
                            'comment' => "DPH poll: {$status}" . ($subStatus ? " ({$subStatus})" : ''),
                        ]);

                        StockService::adjustForStatusChange($order, $dphStatus->orderStatus);
                    }

                    if ($trackingNumber && !$order->tracking_number) {
                        $order->update(['tracking_number' => $trackingNumber]);
                    }
                }

                $this->info("  #{$shipment->order_id}: {$shipment->dph_status} → {$status}");
                $updated++;
            } catch (\Throwable $e) {
                $this->error("  #{$shipment->order_id}: failed — {$e->getMessage()}");
                Log::error('dph:fetch-statuses failed', ['order_id' => $shipment->order_id, 'error' => $e->getMessage()]);
            }
        }

        $this->info("Done. {$updated} shipment(s) updated out of {$shipments->count()} checked.");
        return 0;
    }
}
