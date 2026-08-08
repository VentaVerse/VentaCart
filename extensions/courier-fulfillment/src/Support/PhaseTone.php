<?php

namespace Extensions\CourierFulfillment\Support;

/**
 * Tone map for the common 8-phase fulfillment status spine
 * (CourierFulfillmentInterface::statusPhase()'s 'phase' bucket).
 *
 * Every courier names its statuses differently (QuadX's `for_pickup` says
 * the same physical thing as SPX's own vocabulary will), and every one of
 * those raw strings is unfit to show a packer. The spine gives them one
 * shared bucket; this class gives each bucket one shared colour, so the
 * queue reads as ONE system no matter which courier a row belongs to.
 *
 * TONE PHILOSOPHY, one rule, applied per phase — adapted verbatim from the
 * ERP's own App\Support\ChannelStatusTone (same rule, same wording, ported
 * from "per channel" to "per phase" now that Venta has a single spine
 * instead of one status vocabulary per marketplace):
 *
 *   success  the operator's move, right now. This is the only tone that
 *            resolves to the accent colour, so it is the one thing the eye
 *            lands on when a hundred rows are on screen. Spend it nowhere
 *            else — only `to_book` (go book the shipment) earns it.
 *   warning  waiting on someone the seller CAN chase — a parcel on its way
 *            back (`returning`).
 *   info     waiting on someone the seller CANNOT chase — a courier
 *            creating, holding, or carrying the shipment (`booking`,
 *            `to_handover`, `in_transit`).
 *   neutral  settled. A finished order should recede, which is why
 *            `delivered` is neutral and not the success tone: a page where
 *            "book this now" and "delivered weeks ago" are the same colour
 *            has no signal left.
 *   danger   ended badly — cancellations, failed/lost/damaged deliveries
 *            (`failed`, `cancelled`).
 *
 * An unrecognised phase resolves to neutral — unremarkable, never
 * accidentally alarming or reassuring.
 */
class PhaseTone
{
    /** The 8 spine buckets, in their natural happy-path-then-terminal order. */
    public const PHASES = [
        'to_book',
        'booking',
        'to_handover',
        'in_transit',
        'delivered',
        'failed',
        'cancelled',
        'returning',
    ];

    /** @var array<string, string> */
    private const TONES = [
        'to_book'     => 'success',
        'booking'     => 'info',
        'to_handover' => 'info',
        'in_transit'  => 'info',
        'delivered'   => 'neutral',
        'failed'      => 'danger',
        'cancelled'   => 'danger',
        'returning'   => 'warning',
    ];

    /** Badge tone for a spine phase, or 'neutral' for anything unmapped/null. */
    public static function toneFor(?string $phase): string
    {
        return self::TONES[$phase ?? ''] ?? 'neutral';
    }
}
