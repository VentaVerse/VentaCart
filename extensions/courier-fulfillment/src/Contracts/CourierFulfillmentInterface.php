<?php

namespace Extensions\CourierFulfillment\Contracts;

use App\Models\Order;

/**
 * Contract every courier module implements (QuadX, and future couriers).
 *
 * This is the FULFILLMENT side of shipping — booking a confirmed order with a
 * courier from the dedicated Fulfillment admin page. It is deliberately
 * separate from checkout: Venta's checkout never calls a courier. The admin
 * UI and CourierManager only ever talk to this interface, never to a concrete
 * courier — so adding a new courier never changes any shared code.
 */
interface CourierFulfillmentInterface
{
    /** Machine key, stored on orders.courier_provider. e.g. 'quadx'. */
    public function getKey(): string;

    /** Human label shown in the booking UI. e.g. 'QuadX'. */
    public function getName(): string;

    /** Configured + turned on. */
    public function isEnabled(): bool;

    /**
     * Service levels for the booking form.
     * @return array<int, array{value:string,label:string}>
     */
    public function getServiceTypes(): array;

    /**
     * Parcel categories for the booking form.
     * @return array<int, array{value:string,label:string}>
     */
    public function getParcelOptions(): array;

    /**
     * Shipping-fee payment options (who pays the courier, how).
     * @return array<int, array{value:string,label:string}>
     */
    public function getShippingPaymentOptions(): array;

    /**
     * Book a shipment for an order.
     *
     * @param array $opts {
     *   service:string, parcel:string, weight_kg:float,
     *   length_cm:float, width_cm:float, height_cm:float,
     *   buyer_payment:string ('cod'|'prepaid'), shipping_payment:string
     * }
     * @return array{success:bool, tracking_number?:string, status?:string, meta?:array, error?:string}
     */
    public function createShipment(Order $order, array $opts): array;

    /** @return array{success:bool, error?:string} */
    public function cancelShipment(string $trackingNumber): array;

    /** @return array{success:bool, status?:string, raw?:array, error?:string} */
    public function getTracking(string $trackingNumber): array;

    /**
     * Statuses considered final — the scheduled poll stops checking an order
     * once it reaches one of these, so finished shipments aren't re-polled.
     * @return string[]
     */
    public function terminalStatuses(): array;

    /**
     * Courier statuses from which a shipment can still be canceled.
     * A null/unknown current status counts as cancelable (fresh bookings).
     */
    public function cancelableStatuses(): array;

    /**
     * Buckets a raw courier status onto Venta's common 8-phase fulfillment
     * spine, shared by every courier (see Extensions\CourierFulfillment\
     * Support\PhaseTone for the tone each bucket carries): `to_book`,
     * `booking` (async create in flight), `to_handover` (booked, awaiting
     * courier collection/drop-off), `in_transit`, `delivered`, `failed`
     * (delivery failed / lost / damaged), `cancelled` (booking cancelled),
     * `returning` (coming back to the seller).
     *
     * `to_book` is deliberately NEVER returned here — "no booking exists
     * yet" isn't a courier status, it's the absence of one, so it's the
     * caller's job (see FulfillmentService::phaseFor()) to short-circuit to
     * `to_book` before ever calling this method with a null tracking
     * number. A synchronous courier (like QuadX, whose createShipment()
     * always returns a status) never produces `booking` either — that
     * bucket exists for a courier whose booking creates asynchronously.
     *
     * Also returns the legacy 4-step happy-path stepper derivation for
     * backward compatibility with today's queue view (book -> handover ->
     * transit -> delivered): `failed`/`cancelled`/`returning` are terminal
     * BADGE states on that stepper, not steps of their own.
     *
     * @return array{
     *     phase: string,  // one of the 8 spine buckets above
     *     step: int,      // legacy stepper: 0 Pending,1 Booked,2 Shipped,3 Delivered
     *     failed: bool,   // legacy stepper: true = render step as a failure marker
     * }
     */
    public function statusPhase(?string $status): array;

    /**
     * @param string $format provider format hint (e.g. 'zebra'|'fuji')
     * @return array{success:bool, url?:string, manifest_url?:string, error?:string}
     */
    public function printLabel(string $trackingNumber, string $format): array;

    /**
     * Normalize an incoming webhook payload into provider-agnostic events.
     * @return array<int, array{reference_id:?string, tracking_number:?string, status:string, occurred_at:?string, remarks:?string}>
     */
    public function parseWebhook(array $payload): array;

    /**
     * Declares every `settings` group=courier row this courier reads/writes,
     * so CourierSettingsController's write-allowlist and the settings page's
     * per-courier card are both generated from a single source of truth —
     * adding a courier never requires editing either of those shared files.
     *
     * Each field is:
     *   key:          string   the literal Setting row key (conventionally
     *                          prefixed with getKey(), e.g. 'quadx_mode') —
     *                          renamed keys break stored data, so once shipped
     *                          a key is effectively permanent.
     *   label:        string   form label.
     *   type:         string   'text'|'password'|'select'|'toggle'.
     *   options:      array    for type='select' only —
     *                          array<int,array{value:string,label:string}>,
     *                          same shape as getServiceTypes()/getParcelOptions().
     *   encrypted:    bool     stored via Crypt::encryptString, masked in the
     *                          UI, blank submission keeps the existing value
     *                          (never overwritten with empty). Default false.
     *   mode_scoped:  ?string  when set, this field only applies under that
     *                          mode value (e.g. 'staging'/'production') and
     *                          the settings page groups it under that mode's
     *                          reveal block. The field (of any courier's
     *                          schema) whose own key ends in "_mode" drives
     *                          which group is visible; its select options
     *                          are expected to cover every mode_scoped value
     *                          used elsewhere in the same schema. Default null
     *                          (always visible).
     *   help:         ?string  helper text under the field. Default null.
     *
     * @return array<int, array{key:string,label:string,type:string,options?:array,encrypted?:bool,mode_scoped?:?string,help?:?string}>
     */
    public function settingsSchema(): array;

    /**
     * Tracking URLs extracted from this order's courier_meta, in this
     * courier's own shape — courier_meta is entirely provider-owned (QuadX
     * nests its links under a "delivery"/"pickup" pair at refresh time, or
     * buried inside booked_response's own tracking_links at booking time; a
     * different courier might store one flat url, or none at all). Returned
     * in preference order (best/most useful link first) — the queue page
     * loops this rather than parsing courier_meta's shape itself.
     *
     * @return array<int, array{label:string, url:string}>
     */
    public function trackingLinks(\App\Models\Order $order): array;

    /**
     * Verify the currently saved credentials actually work by pinging the
     * provider. Backs the settings page's "Test Connection" button only —
     * never called on page load.
     *
     * @return array{ok:bool, message:string}
     */
    public function testConnection(): array;

    /**
     * Pull the live status vocabulary + display labels from the provider, to
     * cache as this courier's "{key}_status_labels" setting (used to
     * humanize raw courier statuses in the queue view and to label the
     * settings page's status-mapping table). Backs the settings page's
     * explicit "Refresh from {courier}" button only — never called on page
     * load or on a schedule.
     *
     * @return array{ok:bool, labels?:array<string,string>, error?:string}
     */
    public function refreshStatusLabels(): array;

    /**
     * Curated subset of this courier's status vocabulary that the admin can
     * map to a Venta order status, in display order. A courier's raw API
     * status list (see refreshStatusLabels()) may be larger/noisier than
     * what's useful to expose for mapping — this is that curated list.
     *
     * @return string[]
     */
    public function mappableStatuses(): array;
}
