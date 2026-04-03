<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\OrderStatus;
use App\Models\Setting;
use App\Models\Shipment;
use App\Services\DeliveryPhService;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShipmentController extends Controller
{
    public function getAvailableRates(Order $order)
    {
        if (!DeliveryPhService::isEnabled()) {
            return response()->json(['error' => 'DPH is not enabled.'], 400);
        }

        try {
            $dph = app(DeliveryPhService::class);
            $pickupAddress = $this->getPickupAddress();

            $rates = $dph->getScheduledRates(
                ['province' => $pickupAddress['province'], 'city' => $pickupAddress['city'], 'barangay' => $pickupAddress['barangay'] ?? ''],
                ['province' => $order->shipping_state ?? '', 'city' => $order->shipping_city ?? '', 'barangay' => $order->shipping_barangay ?? ''],
                $this->getOrderDimensions($order),
                $this->getOrderWeight($order)
            );

            $options = [];
            foreach ($rates as $rate) {
                $partnerId = $rate['id'] ?? '';
                $products = $rate['products'] ?? [];
                $estimatedDays = $rate['estimatedDeliveryDays'] ?? null;

                foreach ($products as $product) {
                    $options[] = [
                        'partner_id' => $partnerId,
                        'partner_name' => $rate['name'] ?? $partnerId,
                        'service_type' => $rate['serviceType'] ?? 'scheduled',
                        'size' => $product['size'] ?? '',
                        'price' => (float) ($product['price'] ?? 0),
                        'max_weight' => $product['weight'] ?? null,
                        'estimated_days' => $estimatedDays,
                    ];
                }
            }

            return response()->json(['success' => true, 'rates' => $options]);
        } catch (\Throwable $e) {
            Log::error('Failed to fetch DPH rates for order', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to fetch rates: ' . $e->getMessage()], 500);
        }
    }

    public function book(Request $request, Order $order)
    {
        if ($order->shipment) {
            return response()->json(['error' => 'Order already has a shipment booked.'], 400);
        }

        // Use stored shipping_meta from checkout if available, allow request override
        $meta = $order->shipping_meta;
        $partnerId = $request->input('partner_id', $meta['partner_id'] ?? null);
        $partnerName = $request->input('partner_name', $meta['partner_name'] ?? $order->dph_courier_name ?? '');
        $serviceType = $request->input('service_type', $meta['service_type'] ?? 'scheduled');
        $productSize = $request->input('product_size', $meta['product_size'] ?? '');
        $approximateFee = $request->input('approximate_fee', $order->shipping_cost ?? 0);

        if (!$partnerId) {
            return response()->json(['error' => 'No courier partner ID available. Please select a courier from the rates.'], 422);
        }

        try {
            $dph = app(DeliveryPhService::class);
            $pickupAddress = $this->getPickupAddress();
            $dimensions = $this->getOrderDimensions($order);
            $weight = $this->getOrderWeight($order);

            $pickupDetails = [
                'senderName' => $pickupAddress['name'],
                'contactNumber' => $pickupAddress['phone'],
                'emailAddress' => $pickupAddress['email'],
                'pickupDateTime' => now()->addHours(2)->format('Y-m-d\TH:i'),
                'pickupAddress' => $pickupAddress['address'],
                'pickupCity' => $pickupAddress['city'],
                'province' => $pickupAddress['province'],
                'barangay' => $pickupAddress['barangay'] ?? '',
                'postalCode' => $pickupAddress['postal_code'] ?? '',
            ];

            if ($pickupAddress['lat'] && $pickupAddress['lng']) {
                $pickupDetails['lat'] = (float) $pickupAddress['lat'];
                $pickupDetails['long'] = (float) $pickupAddress['lng'];
            }

            // Resolve postal code if missing (lookup from PH city data)
            $postalCode = $order->shipping_postcode ?? '';
            if (empty($postalCode) && $order->shipping_city) {
                try {
                    $postalCode = \Illuminate\Support\Facades\DB::table('ph_cities')
                        ->where('name', $order->shipping_city)
                        ->value('zip_code') ?? '';
                } catch (\Throwable) {
                    // PH extension may not be installed
                }
            }

            $deliveryDetails = [
                'recipientName' => trim($order->shipping_first_name . ' ' . $order->shipping_last_name),
                'contactNumber' => $order->shipping_phone ?? $order->phone ?? '',
                'emailAddress' => $order->email ?? '',
                'deliveryDateTime' => now()->addDays(3)->format('Y-m-d\TH:i'),
                'deliveryAddress' => $order->shipping_address_1 ?? '',
                'deliveryCity' => $order->shipping_city ?? '',
                'province' => $order->shipping_state ?? '',
                'barangay' => $order->shipping_barangay ?? '',
                'postalCode' => $postalCode,
                'itemDescription' => 'Order #' . $order->id,
                'productSize' => $productSize,
                'length' => DeliveryPhService::cmToInches($dimensions['length']),
                'width' => DeliveryPhService::cmToInches($dimensions['width']),
                'height' => DeliveryPhService::cmToInches($dimensions['height']),
                'weight' => $weight,
                'declaredValue' => (float) $order->total,
            ];

            $refNo = 'ORD-' . $order->id;
            $result = $dph->createPost($pickupDetails, $deliveryDetails, $partnerId, $refNo);

            $postId = $result['postId'] ?? $result['id'] ?? null;

            $shipment = Shipment::create([
                'order_id' => $order->id,
                'dph_post_id' => $postId,
                'dph_partner_id' => $partnerId,
                'dph_partner_name' => $partnerName,
                'dph_service_type' => $serviceType,
                'dph_status' => 'ASSIGNED',
                'approximate_fee' => (float) $approximateFee,
                'booked_at' => now(),
                'meta' => $result,
            ]);

            // Set to Processing — aligns with ASSIGNED webhook mapping
            $processingStatus = OrderStatus::where('store_id', $order->store_id)
                ->where('name', 'Processing')
                ->first();

            if ($processingStatus && (int) $order->order_status_id !== (int) $processingStatus->id) {
                $order->update([
                    'order_status_id' => $processingStatus->id,
                    'dph_courier_name' => $partnerName,
                ]);

                OrderHistory::create([
                    'order_id' => $order->id,
                    'order_status_id' => $processingStatus->id,
                    'comment' => 'Shipment booked via DPH — ' . $partnerName,
                ]);

                StockService::adjustForStatusChange($order, $processingStatus);
            } else {
                $order->update(['dph_courier_name' => $partnerName]);
            }

            return response()->json([
                'success' => true,
                'shipment' => $shipment->fresh(),
                'message' => 'Shipment booked successfully!',
            ]);
        } catch (\Throwable $e) {
            Log::error('DPH booking failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'Booking failed: ' . $e->getMessage()], 500);
        }
    }

    public function cancel(Order $order)
    {
        $shipment = $order->shipment;

        if (!$shipment || !$shipment->dph_post_id) {
            return response()->json(['error' => 'No shipment to cancel.'], 400);
        }

        $nonCancellable = ['DONE_PICKUP', 'ARRIVED_AT_HUB', 'START_DELIVERY', 'DONE', 'CANCEL', 'RETURNED'];
        if (in_array($shipment->dph_status, $nonCancellable)) {
            return response()->json(['error' => 'Shipment cannot be cancelled at this stage.'], 400);
        }

        try {
            $dph = app(DeliveryPhService::class);
            $dph->cancelPost(
                $shipment->dph_post_id,
                $shipment->tracking_number ?? '',
                $shipment->dph_partner_id
            );

            $shipment->update(['dph_status' => 'CANCEL']);

            $cancelledStatus = OrderStatus::where('store_id', $order->store_id)
                ->where('name', 'Canceled')
                ->first();

            if ($cancelledStatus) {
                $order->update(['order_status_id' => $cancelledStatus->id]);

                OrderHistory::create([
                    'order_id' => $order->id,
                    'order_status_id' => $cancelledStatus->id,
                    'comment' => 'Shipment cancelled via DPH.',
                ]);

                StockService::adjustForStatusChange($order, $cancelledStatus);
            }

            return response()->json(['success' => true, 'message' => 'Shipment cancelled.']);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Cancellation failed: ' . $e->getMessage()], 500);
        }
    }

    private function getPickupAddress(): array
    {
        $settings = Setting::where('group', 'shipping')
            ->whereIn('key', [
                'dph_pickup_address', 'dph_pickup_city', 'dph_pickup_province',
                'dph_pickup_barangay', 'dph_pickup_postal_code',
                'dph_pickup_lat', 'dph_pickup_lng',
                'dph_pickup_contact_name', 'dph_pickup_contact_number', 'dph_pickup_contact_email',
            ])
            ->pluck('value', 'key');

        return [
            'address' => $settings->get('dph_pickup_address', ''),
            'city' => $settings->get('dph_pickup_city', ''),
            'province' => $settings->get('dph_pickup_province', ''),
            'barangay' => $settings->get('dph_pickup_barangay', ''),
            'postal_code' => $settings->get('dph_pickup_postal_code', ''),
            'lat' => $settings->get('dph_pickup_lat', ''),
            'lng' => $settings->get('dph_pickup_lng', ''),
            'name' => $settings->get('dph_pickup_contact_name', ''),
            'phone' => $settings->get('dph_pickup_contact_number', ''),
            'email' => $settings->get('dph_pickup_contact_email', ''),
        ];
    }

    private function getOrderDimensions(Order $order): array
    {
        $order->loadMissing('products.product');

        $maxLength = 0;
        $maxWidth = 0;
        $maxHeight = 0;

        foreach ($order->products as $item) {
            if ($item->product) {
                $maxLength = max($maxLength, $item->product->length ?: 0);
                $maxWidth = max($maxWidth, $item->product->width ?: 0);
                $maxHeight = max($maxHeight, $item->product->height ?: 0);
            }
        }

        return [
            'length' => $maxLength ?: 10,
            'width' => $maxWidth ?: 10,
            'height' => $maxHeight ?: 10,
        ];
    }

    private function getOrderWeight(Order $order): float
    {
        $order->loadMissing('products.product', 'products.productVariant');

        $total = 0;
        foreach ($order->products as $item) {
            $weight = $item->productVariant?->weight ?: ($item->product?->weight ?: 0);
            $total += $weight * $item->quantity;
        }

        return $total ?: 0.5;
    }
}
