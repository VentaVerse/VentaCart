<?php

namespace App\Exceptions;

/**
 * Raised while an order is being written when a total line the shopper
 * was shown can no longer be honored: the store credit it applied was
 * spent by another order of theirs in the meantime. The order transaction
 * rolls back and nothing is placed.
 *
 * It extends InvalidArgumentException so every checkout that already
 * turns a refusal into a message on the page shows this one too.
 */
class OrderTotalChangedException extends \InvalidArgumentException
{
}
