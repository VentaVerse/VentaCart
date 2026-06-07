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
     * @param string $format provider format hint (e.g. 'zebra'|'fuji')
     * @return array{success:bool, url?:string, manifest_url?:string, error?:string}
     */
    public function printLabel(string $trackingNumber, string $format): array;

    /**
     * Normalize an incoming webhook payload into provider-agnostic events.
     * @return array<int, array{reference_id:?string, tracking_number:?string, status:string, occurred_at:?string, remarks:?string}>
     */
    public function parseWebhook(array $payload): array;
}
