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
            ['value' => 'small-pouch',  'label' => 'White Small Pouch'],
            ['value' => 'medium-pouch', 'label' => 'Blue Medium Pouch'],
            ['value' => 'big-pouch',    'label' => 'Red Large Pouch'],
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

    /**
     * Re-bucketed onto the common 8-phase spine (see
     * CourierFulfillmentInterface::statusPhase()'s docblock) while keeping
     * the pre-spine legacy 'step'/'failed' derivation byte-for-byte
     * unchanged, so today's queue view stepper (which still reads only
     * those two keys) is provably unaffected by this change.
     */
    public function statusPhase(?string $status): array
    {
        $s = (string) $status;
        [$step, $failed] = self::legacyStepAndFailed($s);

        return [
            'phase'  => self::phaseBucket($s),
            'step'   => $step,
            'failed' => $failed,
        ];
    }

    /**
     * UNCHANGED from before the 8-phase spine existed — the exact logic the
     * queue view's stepper has always rendered off. Kept as its own method,
     * untouched, rather than derived from the new phase bucket, because a
     * few raw statuses (e.g. 'failed_pickup' vs 'failed_delivery') land in
     * the SAME new bucket ('failed') but resolved to DIFFERENT legacy steps
     * depending on where in the journey they broke — information the new
     * bucket alone no longer carries.
     *
     * @return array{0:int, 1:bool}
     */
    private static function legacyStepAndFailed(string $s): array
    {
        $failed = ['failed_pickup','failed_pickup_attempt','failed_delivery','for_reattempt',
                   'returned','return_in_transit','out_for_return','for_return','canceled','cancelled',
                   'rejected','lost','damaged','disposed'];
        if (in_array($s, $failed, true)) {
            // Keep the node where it broke: pickup failures at step 1, else step 2.
            $atPickup = str_contains($s, 'pickup') || $s === 'canceled' || $s === 'cancelled' || $s === 'rejected';
            return [$atPickup ? 1 : 2, true];
        }

        if ($s === 'delivered') {
            return [3, false];
        }

        if (in_array($s, self::SHIPPED_STATUSES, true)) {
            return [2, false];
        }

        // Everything else that means "booked, awaiting pickup" -> step 1.
        return [1, false];
    }

    /** Raw statuses meaning "the courier has it" — shared by the legacy step derivation and the new spine bucketing below. */
    private const SHIPPED_STATUSES = ['picked_up','in_transit','out_for_delivery','for_delivery',
                'in_transit_to_delivery_area','received_at_delivery_hub',
                'received_at_delivery_area','at_sorting_center','at_3pl_facility',
                'received_at_pickup_hub','claimed','confirmed_transit'];

    /**
     * Buckets a raw QuadX status onto the 8-phase spine. Never returns
     * 'to_book' (no booking isn't a courier status) or 'booking' (QuadX's
     * createShipment() is synchronous — see the interface docblock).
     */
    private static function phaseBucket(string $s): string
    {
        if ($s === 'delivered') {
            return 'delivered';
        }

        if (in_array($s, ['canceled', 'cancelled'], true)) {
            return 'cancelled';
        }

        if (in_array($s, ['returned', 'return_in_transit', 'out_for_return', 'for_return'], true)) {
            return 'returning';
        }

        if (in_array($s, ['failed_pickup', 'failed_pickup_attempt', 'failed_delivery', 'for_reattempt',
                'rejected', 'lost', 'damaged', 'disposed', 'failed_return'], true)) {
            return 'failed';
        }

        if (in_array($s, self::SHIPPED_STATUSES, true)) {
            return 'in_transit';
        }

        // Everything else (empty/pending/confirmed/for_pickup/searching_for_rider/
        // for_acceptance/for_consolidation/for_dropoff/for_payment/paid/...) means
        // "booked, awaiting courier collection/drop-off".
        return 'to_handover';
    }

    /**
     * QuadX has no fee-quoting API (it prices by parcel category at
     * remittance time), so no estimate is ever shown for it — the interface's
     * sanctioned null default. Deliberately NOT derived from any rate table:
     * inventing a figure QuadX never quoted would be a lie on a screen the
     * operator commits money from.
     */
    public function estimateFee(Order $order, array $options): ?object
    {
        return null;
    }

    /**
     * QuadX schedules pickup on its own side after booking (no slot choice in
     * its API), so the booking form renders no slot picker — the interface's
     * sanctioned empty default.
     */
    public function getPickupSlots(Order $order): array
    {
        return [];
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

    /**
     * Exactly today's stored keys (quadx_enabled, quadx_mode, the four
     * mode-scoped credential pairs, quadx_default_service/parcel) — declaring
     * anything else here would change what CourierSettingsController persists
     * and break the staging verification this module already passed.
     */
    public function settingsSchema(): array
    {
        return [
            [
                'key'   => 'quadx_enabled',
                'label' => 'Enable QuadX',
                'type'  => 'toggle',
                'help'  => 'Book confirmed orders with QuadX from the Fulfillment page.',
            ],
            [
                'key'     => 'quadx_mode',
                'label'   => 'Mode',
                'type'    => 'select',
                'options' => [
                    ['value' => 'staging',    'label' => 'Staging'],
                    ['value' => 'production', 'label' => 'Production'],
                ],
            ],
            [
                'key'         => 'quadx_staging_api_key',
                'label'       => 'API Key',
                'type'        => 'password',
                'encrypted'   => true,
                'mode_scoped' => 'staging',
            ],
            [
                'key'         => 'quadx_staging_secret_key',
                'label'       => 'Secret Key',
                'type'        => 'password',
                'encrypted'   => true,
                'mode_scoped' => 'staging',
            ],
            [
                'key'         => 'quadx_production_api_key',
                'label'       => 'API Key',
                'type'        => 'password',
                'encrypted'   => true,
                'mode_scoped' => 'production',
            ],
            [
                'key'         => 'quadx_production_secret_key',
                'label'       => 'Secret Key',
                'type'        => 'password',
                'encrypted'   => true,
                'mode_scoped' => 'production',
            ],
            [
                'key'     => 'quadx_default_service',
                'label'   => 'Default service',
                'type'    => 'select',
                'options' => $this->getServiceTypes(),
                'help'    => 'Pre-selected service on the booking form.',
            ],
            [
                'key'     => 'quadx_default_parcel',
                'label'   => 'Default parcel',
                'type'    => 'select',
                'options' => $this->getParcelOptions(),
                'help'    => 'Pre-selected parcel category on the booking form.',
            ],
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

    /**
     * Preference order: courier_meta.tracking_links.delivery, then .pickup
     * (both refreshed via getTracking()'s poll/refresh path), else whatever
     * shape QuadX returned at booking time inside booked_response.tracking_links
     * (string, nested array, or absent — hunted for recursively).
     */
    public function trackingLinks(Order $order): array
    {
        $meta = $order->courier_meta ? (json_decode($order->courier_meta, true) ?: []) : [];
        if (!is_array($meta)) {
            return [];
        }

        $links = [];
        $metaLinks = $meta['tracking_links'] ?? null;
        if (is_array($metaLinks)) {
            foreach (['delivery' => 'Delivery', 'pickup' => 'Pickup'] as $k => $label) {
                $url = $metaLinks[$k] ?? null;
                if (is_string($url) && str_starts_with($url, 'http')) {
                    $links[] = ['label' => $label, 'url' => $url];
                }
            }
        }

        if (empty($links)) {
            $found = self::findFirstUrl($meta['booked_response']['tracking_links'] ?? null);
            if ($found) {
                $links[] = ['label' => 'Track', 'url' => $found];
            }
        }

        return $links;
    }

    /** Recursively hunt for the first http(s) URL — booked_response's tracking_links shape varies. */
    private static function findFirstUrl(mixed $value): ?string
    {
        if (is_string($value)) {
            return str_starts_with($value, 'http') ? $value : null;
        }
        if (is_array($value)) {
            foreach ($value as $v) {
                if ($found = self::findFirstUrl($v)) {
                    return $found;
                }
            }
        }
        return null;
    }

    public function testConnection(): array
    {
        if (!$this->client->isConfigured()) {
            return ['ok' => false, 'message' => 'API key/secret not set.'];
        }
        $res = $this->client->getStatuses();
        return $res['ok']
            ? ['ok' => true, 'message' => 'Connected — QuadX reachable and credentials valid.']
            : ['ok' => false, 'message' => 'Failed: ' . ($res['error'] ?? 'unknown (HTTP ' . $res['status'] . ')')];
    }

    public function refreshStatusLabels(): array
    {
        if (!$this->client->isConfigured()) {
            return ['ok' => false, 'error' => 'API key/secret not set — cannot refresh statuses.'];
        }

        $res = $this->client->getStatuses();
        if (!$res['ok']) {
            return ['ok' => false, 'error' => 'Refresh failed: ' . ($res['error'] ?? 'unknown error')];
        }

        $labels = [];
        foreach ($res['body'] as $s) {
            if (!empty($s['name'])) {
                $labels[$s['name']] = $s['label'] ?? ucwords(str_replace('_', ' ', $s['name']));
            }
        }

        return ['ok' => true, 'labels' => $labels];
    }

    /** Curated subset presented on the status-mapping table — unchanged from before this became courier-owned data. */
    public function mappableStatuses(): array
    {
        return ['for_pickup', 'picked_up', 'in_transit', 'out_for_delivery',
                'delivered', 'failed_pickup', 'failed_delivery', 'returned', 'canceled'];
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
            // QuadX insures via declared_value (~1% fee on that amount). Omitting
            // this field entirely makes QuadX silently DEFAULT declared_value to
            // the order total — auto-insuring every booking with an unwanted fee.
            // We always send it explicitly: 0 = uninsured (default), order total
            // = insured (admin opt-in via the "Insure shipment" checkbox).
            'declared_value'   => (bool) ($opts['insure'] ?? false) ? (float) $order->total : 0,
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
        return \Extensions\CourierFulfillment\FulfillmentService::isCodOrder($order);
    }
}
