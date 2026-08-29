<?php

namespace Tests\Support;

use App\Models\Setting;

/**
 * The fulfillment module no longer guesses its booked, unbooked and cancel
 * statuses from a placement (owner ruling 2026-08-30): a real store has
 * them chosen on the hub, or pinned by the 2026_08_30_130000 migration at
 * upgrade. Tests place a status and expect to book straight away, which is
 * what a store looks like AFTER that choice, so placing a status here also
 * selects it, once, when nothing is selected yet. A test that wants the
 * unselected state deletes the setting explicitly.
 */
class FulfillmentSettings
{
    private const KEY_FOR = [
        'to_handover' => 'booked_order_status_id',
        'to_pack'     => 'unbooked_order_status_id',
        'cancelled'   => 'cancel_order_status_id',
    ];

    public static function selectFor(?string $placement, int $orderStatusId): void
    {
        $key = self::KEY_FOR[$placement ?? ''] ?? null;
        if ($key === null) {
            return;
        }
        Setting::firstOrCreate(['group' => 'courier', 'key' => $key], ['value' => (string) $orderStatusId]);
    }
}
