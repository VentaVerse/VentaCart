<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The fulfillment mapper used to write "Courier status: for_pickup" into an
 * order's history, and that row reached the customer's order page and their
 * status email whenever the mapping notified them. It now writes the
 * courier's name and the status in the courier's words ("QuadX: For
 * pickup"). Rows written before that keep the old text, and a customer
 * opening a past order still read the code.
 *
 * Continuity: an existing store gets those rows rewritten once, here, with
 * the same words the mapper would write today. The raw code stays on
 * orders.courier_status and in courier_meta; only the sentence shown to
 * people changes. A fresh install has no such rows and skips this.
 */
return new class extends Migration
{
    private const NAMES = ['quadx' => 'QuadX', 'spx' => 'SPX', 'lbc' => 'LBC'];

    public function up(): void
    {
        // courier_provider is the fulfillment extension's column; a store
        // that never installed it never had the mapper write these rows.
        if (\App\Support\InstallState::isFreshInstall() || ! Schema::hasTable('order_histories') || ! Schema::hasColumn('orders', 'courier_provider')) {
            return;
        }

        $rows = DB::table('order_histories as h')
            ->join('orders as o', 'o.id', '=', 'h.order_id')
            ->where('h.comment', 'like', 'Courier status: %')
            ->get(['h.id', 'h.comment', 'o.courier_provider', 'o.shipping_courier_name']);

        foreach ($rows as $row) {
            $code = trim(substr((string) $row->comment, strlen('Courier status: ')));
            if ($code === '') {
                continue;
            }

            DB::table('order_histories')->where('id', $row->id)->update([
                'comment' => self::sentence((string) $row->courier_provider, $row->shipping_courier_name, $code),
            ]);
        }
    }

    private static function sentence(string $provider, ?string $courierName, string $code): string
    {
        $name = $courierName ?: (self::NAMES[$provider] ?? ucfirst($provider ?: 'Courier'));

        // The extension's own labeller when the extension is present (the
        // labels the admin refreshed, a courier's known vocabulary), else the
        // same last resort it uses: the code with its underscores spaced.
        $label = class_exists(\Extensions\CourierFulfillment\CourierStatusLabel::class)
            ? \Extensions\CourierFulfillment\CourierStatusLabel::for($provider, $code)
            : ucfirst(str_replace(['_', '-'], ' ', $code));

        return $name . ': ' . $label;
    }

    public function down(): void
    {
        // The old text was the defect; nothing to restore.
    }
};
