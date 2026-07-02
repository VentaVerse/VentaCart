<?php

namespace Extensions\CourierFulfillment\Couriers\QuadX;

use App\Models\Order;
use App\Models\Setting;
use Extensions\CourierFulfillment\Contracts\CourierFulfillmentInterface;

/**
 * QuadX courier (a Philippine courier, LBC-backed).
 *
 * Implements the generic fulfillment contract. All QuadX-specific knowledge
 * lives here + QuadXClient: auth, parcel categories, services, the
 * seller_payment_method field, and name-based address resolution.
 */
class QuadXFulfillment implements CourierFulfillmentInterface
{
    public function __construct(
        private ?QuadXClient $client = null,
        private ?QuadXLocationResolver $resolver = null,
    ) {
        $this->client = $client ?? new QuadXClient();
        $this->resolver = $resolver ?? new QuadXLocationResolver();
    }

    public function getKey(): string
    {
        return 'quadx';
    }

    public function getName(): string
    {
        return 'QuadX';
    }

    public function isEnabled(): bool
    {
        $on = Setting::where('group', 'courier')->where('key', 'quadx_enabled')->value('value');
        return (bool) $on && $this->client->isConfigured();
    }

    public function getServiceTypes(): array
    {
        return [
            ['value' => 'next_day',        'label' => 'Next Day'],
            ['value' => 'same_day_pickup', 'label' => 'Same Day Pickup'],
        ];
    }

    public function getParcelOptions(): array
    {
        return [
            ['value' => 'small-pouch',  'label' => 'Small Pouch'],
            ['value' => 'medium-pouch', 'label' => 'Medium Pouch'],
            ['value' => 'big-pouch',    'label' => 'Big Pouch'],
            ['value' => 'box',          'label' => 'Box'],
            ['value' => 'oversized',    'label' => 'Oversized'],
        ];
    }

    /**
     * Real QuadX API capability: seller_payment_method = cash | gcash | null.
     * The API does NOT distinguish GCash "Scan QR" vs "Wallet" (both are just
     * gcash) — that split exists only in QuadX's own dashboard. "Receiver pays
     * the fee" (COD shipping on top of item value) is a separate mechanism
     * (a shipping line item added to the total) and isn't offered here yet.
     */
    public function getShippingPaymentOptions(): array
    {
        return [
            ['value' => 'cash',    'label' => "I'll shoulder it — Cash on pickup"],
            ['value' => 'gcash',   'label' => "I'll shoulder it — GCash at pickup"],
            ['value' => 'net_off', 'label' => "I'll shoulder it — Deduct from my remittance / bill me"],
        ];
    }

    public function createShipment(Order $order, array $opts): array
    {
        $payload = $this->buildPayload($order, $opts);

        $res = $this->client->createOrder($payload);
        if (!$res['ok']) {
            return ['success' => false, 'error' => $res['error'] ?? 'QuadX booking failed.'];
        }

        $b = $res['body'];
        return [
            'success'         => true,
            'tracking_number' => $b['tracking_number'] ?? null,
            'status'          => $b['status'] ?? 'for_pickup',
            'meta'            => [
                'quadx_id'         => $b['id'] ?? null,
                'service'          => $opts['service'] ?? null,
                'parcel'           => $opts['parcel'] ?? null,
                'shipping_payment' => $opts['shipping_payment'] ?? 'net_off',
                'reference_id'     => $b['reference_id'] ?? (string) $order->id,
                'booked_response'  => $b,
            ],
        ];
    }

    public function cancelShipment(string $trackingNumber): array
    {
        $res = $this->client->cancelOrder($trackingNumber);
        return $res['ok']
            ? ['success' => true]
            : ['success' => false, 'error' => $res['error'] ?? 'Cancel failed.'];
    }

    public function getTracking(string $trackingNumber): array
    {
        $res = $this->client->getOrder($trackingNumber);
        if (!$res['ok']) {
            return ['success' => false, 'error' => $res['error'] ?? 'Tracking lookup failed.'];
        }
        $b = $res['body'];
        return [
            'success'  => true,
            'status'   => $b['status'] ?? null,
            'raw'      => $b,
            'links'    => (isset($b['tracking_links']) && is_array($b['tracking_links'])) ? $b['tracking_links'] : null,
            'timeline' => self::timelineFromTat($b['tat'] ?? null),
        ];
    }

    public function terminalStatuses(): array
    {
        // Final states — the poll stops re-checking once an order reaches one.
        // failed_delivery is NOT terminal (rider retries), so we keep polling it.
        return ['delivered', 'canceled', 'cancelled', 'returned', 'failed_return'];
    }

    public function cancelableStatuses(): array
    {
        return ['pending', 'confirmed', 'for_pickup', 'searching_for_rider',
                'failed_pickup_attempt', 'for_acceptance', 'for_consolidation',
                'for_dropoff', 'for_payment', 'paid'];
    }

    /** Normalize QuadX's tat map into a chronological event list. @return array<int,array{status:string,at:int}> */
    public static function timelineFromTat(?array $tat): array
    {
        if (empty($tat)) {
            return [];
        }
        $excluded = ['sla_start', 'first_pickup_at', 'first_estimated_delivery_date'];
        $events = [];
        foreach ($tat as $status => $at) {
            if (in_array($status, $excluded, true)) {
                continue;
            }
            if (!is_int($at)) {
                continue;
            }
            $events[] = ['status' => (string) $status, 'at' => $at];
        }
        usort($events, fn ($a, $b) => $a['at'] <=> $b['at']);
        return array_values($events);
    }

    public function statusPhase(?string $status): array
    {
        $s = (string) $status;

        $failed = ['failed_pickup','failed_pickup_attempt','failed_delivery','for_reattempt',
                   'returned','return_in_transit','out_for_return','for_return','canceled','cancelled',
                   'rejected','lost','damaged','disposed'];
        if (in_array($s, $failed, true)) {
            // Keep the node where it broke: pickup failures at step 1, else step 2.
            $atPickup = str_contains($s, 'pickup') || $s === 'canceled' || $s === 'cancelled' || $s === 'rejected';
            return ['step' => $atPickup ? 1 : 2, 'failed' => true];
        }

        if ($s === 'delivered') {
            return ['step' => 3, 'failed' => false];
        }

        $shipped = ['picked_up','in_transit','out_for_delivery','for_delivery',
                    'in_transit_to_delivery_area','received_at_delivery_hub',
                    'received_at_delivery_area','at_sorting_center','at_3pl_facility',
                    'received_at_pickup_hub','claimed','confirmed_transit'];
        if (in_array($s, $shipped, true)) {
            return ['step' => 2, 'failed' => false];
        }

        // Everything else that means "booked, awaiting pickup" -> step 1.
        return ['step' => 1, 'failed' => false];
    }

    public function printLabel(string $trackingNumber, string $format): array
    {
        $type = in_array($format, ['zebra', 'fuji'], true) ? $format : 'zebra';
        $res = $this->client->printAwb($trackingNumber, $type);
        if (!$res['ok']) {
            return ['success' => false, 'error' => $res['error'] ?? 'AWB print failed.'];
        }
        $msg = $res['body']['message'] ?? [];
        return [
            'success'      => true,
            'url'          => $msg['awb'] ?? null,
            'manifest_url' => $msg['v35'] ?? null,
        ];
    }

    public function parseWebhook(array $payload): array
    {
        $events = [];
        foreach (($payload['data'] ?? []) as $e) {
            $events[] = [
                'reference_id'    => $e['reference_id'] ?? null,
                'tracking_number' => $e['tracking_number'] ?? null,
                'status'          => $e['status'] ?? 'unknown',
                'occurred_at'     => $e['status_updated_at'] ?? null,
                'remarks'         => $e['remarks'] ?? null,
            ];
        }
        return $events;
    }

    // --- Payload builder --------------------------------------------------

    private function buildPayload(Order $order, array $opts): array
    {
        $buyerPayment = ($opts['buyer_payment'] ?? null)
            ?: ($this->orderIsCod($order) ? 'cod' : 'prepaid');

        $payload = [
            'status'           => 'for_pickup',
            'service'          => $opts['service'] ?? 'next_day',
            'reference_id'     => (string) $order->id,
            'delivery_address' => $this->deliveryAddress($order),
            'pickup_address'   => $this->pickupAddress(),
            'contact_number'   => $order->shipping_phone ?: ($order->phone ?: ''),
            'buyer_name'       => trim($order->shipping_first_name . ' ' . $order->shipping_last_name),
            // QuadX prices by parcel category (shipment), not by weight/dimensions —
            // it accepts orders with no parcel object, so we don't collect or send them.
            'payment_provider' => 'other',
            'payment_method'   => $buyerPayment === 'cod' ? 'cod' : 'other',
            'items'            => $this->items($order),
            'currency'         => 'PHP',
            'shipment'         => $opts['parcel'] ?? 'box',
            'total'            => (float) $order->total,
            'async_address_validate' => false,
        ];

        // Seller's shipping-fee payment. Omit entirely for net-off (deduct/bill).
        $shippingPayment = $opts['shipping_payment'] ?? 'net_off';
        if (in_array($shippingPayment, ['cash', 'gcash'], true)) {
            $payload['seller_payment_method'] = $shippingPayment;
        }

        return $payload;
    }

    private function deliveryAddress(Order $order): array
    {
        return [
            'name'          => trim($order->shipping_first_name . ' ' . $order->shipping_last_name) ?: 'Customer',
            'mobile_number' => $order->shipping_phone ?: ($order->phone ?: ''),
            'line_1'        => $order->shipping_address_1 ?: '-',
            'district'      => $order->shipping_district ?: ($order->shipping_barangay ?: null),
            'city'          => $this->resolver->normalizeCity($order->shipping_city, $order->shipping_state),
            'state'         => $this->resolver->normalizeState($order->shipping_state),
            'postal_code'   => $order->shipping_postcode ?: '-',
            'country'       => 'PH',
        ];
    }

    private function pickupAddress(): array
    {
        $s = fn ($k, $d = null) => Setting::where('group', 'shipping')->where('key', $k)->value('value') ?: $d;
        return [
            'name'          => $s('pickup_contact_name', 'Sender'),
            'mobile_number' => $s('pickup_contact_number', ''),
            'line_1'        => $s('pickup_address', '-'),
            'district'      => $s('pickup_barangay'),
            'city'          => $this->resolver->normalizeCity($s('pickup_city'), $s('pickup_province')),
            'state'         => $this->resolver->normalizeState($s('pickup_province')),
            'postal_code'   => $s('pickup_postal_code', '-'),
            'country'       => 'PH',
        ];
    }

    private function items(Order $order): array
    {
        $items = [];
        foreach ($order->products as $p) {
            $items[] = [
                'type'        => 'product',
                'description' => $p->name ?: 'Item',
                'amount'      => (float) $p->price,
                'quantity'    => (int) $p->quantity,
            ];
        }
        if ($items === []) {
            $items[] = ['type' => 'product', 'description' => 'Order #' . $order->id, 'amount' => (float) $order->total, 'quantity' => 1];
        }
        return $items;
    }

    private function orderIsCod(Order $order): bool
    {
        return str_contains(strtolower((string) $order->payment_method), 'cod')
            || str_contains(strtolower((string) $order->payment_method), 'cash on delivery');
    }
}
