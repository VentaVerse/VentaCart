<?php

namespace Extensions\CourierFulfillment\Contracts;

/**
 * A courier whose shipments can be tracked on a public page addressed by
 * the tracking number alone.
 *
 * Optional, on purpose. The main contract's trackingLinks() carries the
 * per-shipment URLs a courier hands back through its API, and some hand
 * back none: QuadX answered `links: []` on a live refresh of a booked
 * parcel. A courier that also runs a public tracker implements this so the
 * shopper still gets something to click; one that does not simply leaves
 * it unimplemented and the number stays plain text, which is honest.
 */
interface ProvidesPublicTrackingUrl
{
    /**
     * @param array<string, mixed> $courierMeta the booking's own courier_meta,
     *   decoded. A courier with sandbox and live environments keeps a
     *   `mode` there from booking time, so a parcel booked against the
     *   sandbox links to the sandbox tracker even after the store goes live,
     *   and never the other way round.
     */
    public function publicTrackingUrl(string $trackingNumber, array $courierMeta): ?string;
}
