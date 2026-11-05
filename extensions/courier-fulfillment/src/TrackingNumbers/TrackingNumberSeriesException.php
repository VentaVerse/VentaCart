<?php

namespace Extensions\CourierFulfillment\TrackingNumbers;

use RuntimeException;

/** A tracking number could not be issued or settled. The message is safe to show staff. */
class TrackingNumberSeriesException extends RuntimeException
{
}
