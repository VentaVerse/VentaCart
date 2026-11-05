<?php

namespace Extensions\CourierFulfillment\Couriers\Lbc;

use App\Models\Order;
use Carbon\CarbonInterface;
use Extensions\CourierFulfillment\Models\PickupAddress;

/** Everything one pickup request is built from, and what would stop it. */
final class LbcPickupDraft
{
    /** @param string[] $blockers reasons the request must not be sent */
    public function __construct(
        public readonly Order $order,
        public readonly PickupAddress $from,
        public readonly LbcParcel $parcel,
        public readonly LbcAccount $account,
        public readonly LbcArea $origin,
        public readonly LbcArea $destination,
        public readonly CarbonInterface $transactionAt,
        public readonly CarbonInterface $preferredAt,
        public readonly string $mode,
        public readonly array $blockers,
    ) {
    }
}
