<?php

namespace App\Exceptions;

/**
 * Raised when Place order arrives for a cart whose order is still being
 * placed by an earlier request: the order is written, but its payment step
 * has not finished. The earlier request sends the shopper on; this one
 * places nothing.
 *
 * It extends InvalidArgumentException so every checkout that already turns
 * a refusal into a message on the page shows this one too.
 */
class OrderAlreadyBeingPlacedException extends \InvalidArgumentException
{
    public function __construct()
    {
        parent::__construct(
            'Your order is already being placed. Please wait a moment, then check your orders before trying again.'
        );
    }
}
