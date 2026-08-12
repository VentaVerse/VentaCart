<?php

namespace Extensions\CourierFulfillment\Support;

use App\Models\Order;

/**
 * The sanctioned default for CourierFulfillmentInterface::checkServiceability():
 * "I cannot answer that in advance."
 *
 * PHP interfaces carry no method bodies, so this trait IS the default the
 * interface's docblock promises. A courier with no coverage API (or one whose
 * only authority is the booking call itself) uses this and is never forced to
 * pretend it knows: `unknown` renders as a still-clickable card, so the
 * operator can go and ask the courier directly.
 */
trait AnswersUnknownServiceability
{
    public function checkServiceability(Order $order): ServiceabilityVerdict
    {
        return ServiceabilityVerdict::unknown();
    }
}
