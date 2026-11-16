<?php

namespace App\Exceptions;

/**
 * Thrown inside the order transaction when the cart was linked to another
 * order between reading it and linking it, so the order just written is
 * rolled back. Checkout catches it and answers with the other order; it
 * never reaches a shopper.
 */
class CartLinkTakenException extends \RuntimeException {}
