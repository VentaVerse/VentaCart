<?php

namespace Extensions\CourierFulfillment;

use App\Contracts\OrderEmailPolicy;

/**
 * The store's automatic email list, kept on Status Mapping.
 *
 * Bound into core while this extension boots, so every change nobody is
 * watching (a booking, a courier update, a payment, an API call that does
 * not say) emails on the statuses ticked there.
 */
class FulfillmentEmailPolicy implements OrderEmailPolicy
{
    public function emailsOn(int $orderStatusId): bool
    {
        return NotifyMap::notifies($orderStatusId);
    }
}
