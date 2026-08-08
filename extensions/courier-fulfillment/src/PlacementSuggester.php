<?php

namespace Extensions\CourierFulfillment;

/**
 * Name-based placement SUGGESTIONS, offered on the Status placement page and
 * applied only when the admin asks for them.
 *
 * These lists used to live in core's OrderStatusSeeder, where they seeded
 * placements automatically. The owner ruled the module seeds nothing
 * (2026-08-09), and the mapping is module data, so they moved here and lost
 * their automatic arm: nothing calls this during install, seeding, or a
 * request that writes. The page renders each unplaced status's suggestion as
 * a data attribute, and the admin's "Suggest placements" button fills the
 * empty selects with them, still unsaved until Save is pressed.
 *
 * The names are the ones an OpenCart-era store actually arrives with (the
 * seeder mirrors OpenCart's own defaults) plus the two fulfillment-shaped
 * names those stores use for the handover step. An unrecognised name gets no
 * suggestion at all rather than a guess.
 */
class PlacementSuggester
{
    /** @var array<string, array<int,string>> placement => status names */
    private const NAMES = [
        'unpaid' => ['Unpaid', 'Pending', 'Quote Ready for Payment'],
        'cancelled' => [
            'Canceled', 'Cancelled', 'Canceled Reversal', 'Denied', 'Expired', 'Failed',
            'Refunded', 'Reversed', 'Voided', 'Chargeback',
        ],
        'delivered'   => ['Delivered', 'Complete'],
        'shipping'    => ['Shipped', 'In Transit to Manila'],
        'to_handover' => ['Order Being Shipped', 'Done Pickup', 'Start Pickup'],
        'to_pack'     => ['Processing', 'Processed', 'Payment Received'],
    ];

    /** The suggested placement for a status name, or null when unrecognised. */
    public static function suggest(string $name): ?string
    {
        $needle = mb_strtolower(trim($name));

        foreach (self::NAMES as $placement => $names) {
            foreach ($names as $candidate) {
                if (mb_strtolower($candidate) === $needle) {
                    return $placement;
                }
            }
        }

        return null;
    }
}
