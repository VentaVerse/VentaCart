<?php

namespace Extensions\CourierFulfillment\Contracts;

/**
 * A courier that says what the shipment actually cost the store.
 *
 * Optional, like ProvidesPublicTrackingUrl. Some couriers quote a price and
 * charge it; some bill against an account and never say; LBC issues no figure
 * at all because NGD is billed against advances. A courier that cannot answer
 * leaves this unimplemented and the order simply has no shipping cost
 * recorded, which reads as "not known" rather than as free.
 *
 * This is the cost to the STORE, not what the customer was charged for
 * shipping. Those are different numbers and the order page shows both: the
 * customer's shipping sits in the ledger above the total, this one below it.
 */
interface ReportsShippingCost
{
    /**
     * What this booking cost, or null when the courier did not say.
     *
     * @param array<string, mixed> $courierMeta the booking's own courier_meta, decoded
     */
    public function actualShippingCost(array $courierMeta): ?float;
}
