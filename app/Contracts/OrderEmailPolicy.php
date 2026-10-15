<?php

namespace App\Contracts;

/**
 * Which order statuses email the customer when nobody chose for them.
 *
 * Core sends a status email only when someone asks: a person ticking Notify
 * on the order page, or an API caller passing notify. The automatic answer,
 * for changes nobody is watching (a courier update, a payment, a fulfilment
 * booking, an ERP call that says nothing), belongs to whichever extension
 * automates order handling, the way it did in OpenCart. That extension
 * binds this contract while it boots; with none bound, automation emails
 * nobody.
 */
interface OrderEmailPolicy
{
    public function emailsOn(int $orderStatusId): bool;
}
