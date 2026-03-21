<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\OrderHistory;
use App\Models\OrderStatus;
use App\Models\Setting;
use App\Models\Shipment;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DphWebhookController extends Controller
{
    private const STATUS_MAP = [
        'ASSIGNED' => 'booked',
        'ACCEPTED' => 'confirmed',
        'ASSIGNING_RIDER' => 'confirmed',
        'START_PICKUP' => 'pickup-started',
        'DONE_PICKUP' => 'picked-up',
        'ARRIVED_AT_HUB' => 'in-transit',
        'START_DELIVERY' => 'out-for-delivery',
        'DONE' => 'delivered',
        'FAILED_PICKUP' => 'failed-pickup',
        'FAILED_DELIVERY' => 'failed-delivery',
        'CANCEL' => 'canceled',
        'IN_TRANSIT_FOR_RETURN' => 'returning',
        'RETURNED' => 'returned',
    ];

    public function __invoke(Request $request)
    {
        $secret = $request->query('secret');
        $storedSecret = Setting::where('group', 'shipping')
            ->where('key', 'dph_webhook_secret')
            ->value('value');

        if (!$secret || !$storedSecret || !hash_equals($storedSecret, $secret)) {
            Log::warning('DPH webhook: invalid secret');
            return response('Unauthorized', 401);
        }

        $postId = $request->query('postId');
        $status = $request->query('status');
        $subStatus = $request->query('subStatus');
        $trackingNumber = $request->query('trackingNumber');
        $trackingUrl = $request->query('trackingUrl');
        $shareableLink = $request->query('shareableLink');
        $feeApproximate = $request->query('fee_approximate');
        $feeActual = $request->query('fee_actual');
        $feeFinal = $request->query('fee_final');
        $remarks = $request->query('remarks');
        $courierName = $request->query('courierName');

        Log::info('DPH webhook received', ['postId' => $postId, 'status' => $status, 'subStatus' => $subStatus]);

        if (!$postId) {
            Log::warning('DPH webhook: missing postId');
            return response('OK', 200);
        }

        $shipment = Shipment::where('dph_post_id', $postId)->first();

        if (!$shipment) {
            Log::warning('DPH webhook: shipment not found', ['postId' => $postId]);
            return response('OK', 200);
        }

        if ($shipment->dph_status === $status && $shipment->dph_sub_status === $subStatus) {
            return response('OK', 200);
        }

        $shipment->events()->create([
            'status' => $status ?? 'UNKNOWN',
            'sub_status' => $subStatus,
            'remarks' => $remarks,
            'meta' => $request->query(),
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

        $order = $shipment->order;

        if ($order && isset(self::STATUS_MAP[$status])) {
            $orderStatusSlug = self::STATUS_MAP[$status];
            $storeId = $order->store_id;

            $newOrderStatus = OrderStatus::where('store_id', $storeId)
                ->where('slug', $orderStatusSlug)
                ->first();

            if ($newOrderStatus && (int) $order->order_status_id !== (int) $newOrderStatus->id) {
                $order->update(['order_status_id' => $newOrderStatus->id]);

                OrderHistory::create([
                    'order_id' => $order->id,
                    'order_status_id' => $newOrderStatus->id,
                    'comment' => "DPH: {$status}" . ($subStatus ? " ({$subStatus})" : '') . ($remarks ? " - {$remarks}" : ''),
                ]);

                StockService::adjustForStatusChange($order, $newOrderStatus);
            }

            if ($trackingNumber && !$order->tracking_number) {
                $order->update(['tracking_number' => $trackingNumber]);
            }
        }

        return response('OK', 200);
    }
}
