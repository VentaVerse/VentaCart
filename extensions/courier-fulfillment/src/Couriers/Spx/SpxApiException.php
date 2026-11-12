<?php

namespace Extensions\CourierFulfillment\Couriers\Spx;

/**
 * Thrown by SpxClient::post() whenever SPX answers with a well-formed
 * `{ret_code, message, data}` envelope whose ret_code is non-zero. Carries
 * the raw ret_code so callers can branch on the SPX error family (auth
 * 1xxx, parameter 11xxx, account 12xxx, order 13xxx, business rule 10xxx)
 * without matching the message text.
 *
 * $httpStatus is the HTTP status the envelope came with. An envelope under
 * HTTP 500 or above is SPX failing, not SPX deciding: an order sent with it
 * may still have been created, so callers treat it as an unknown outcome
 * rather than a refusal.
 */
class SpxApiException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $retCode, public readonly int $httpStatus = 200)
    {
        parent::__construct($message);
    }

    /** SPX failed on its side: the outcome of what was sent is not known. */
    public function isServerError(): bool
    {
        return $this->httpStatus >= 500;
    }
}
