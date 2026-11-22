<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Splits product_specials into two tables.
 *
 * product_specials keeps only timed special prices: one price per product
 * with a start and an end, for every customer group. Everything that is
 * always on moves to product_discounts: prices for one customer group (or
 * all of them), prices that start at a quantity above one, rows made as
 * standing prices ("override"), and rows with no end date.
 *
 * Rows whose end has passed are dropped, as they no longer apply. Where
 * several old rows land on the same product, group and quantity, the one
 * the cart used to charge is kept: lowest priority number, then lowest
 * price, then the earliest row.
 *
 * The move runs only while the table still has the old display_mode
 * column, so running it again changes nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_discounts')) {
            Schema::create('product_discounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_group_id')->nullable()->constrained()->cascadeOnDelete();
                $table->unsignedInteger('quantity')->default(1);
                $table->string('price_type', 10)->default('fixed');
                $table->decimal('value', 15, 2);
                $table->timestamps();

                $table->index(['product_id', 'customer_group_id', 'quantity'], 'pd_product_group_quantity_index');
            });
        }

        if (! Schema::hasColumn('product_specials', 'display_mode')) {
            return;
        }

        DB::transaction(function () {
            $this->moveRows();
        });

        // The new index goes in first: MySQL needs one that starts with
        // product_id for its foreign key while the old ones are dropped.
        Schema::table('product_specials', function (Blueprint $table) {
            $table->index(['product_id', 'date_start', 'date_end'], 'ps_product_dates_index');
        });

        Schema::table('product_specials', function (Blueprint $table) {
            $table->dropIndex('ps_product_display_dates_index');
            $table->dropIndex(['product_id', 'priority']);
        });

        // The foreign key goes before its column, which MySQL requires.
        Schema::table('product_specials', function (Blueprint $table) {
            $table->dropForeign(['customer_group_id']);
        });

        Schema::table('product_specials', function (Blueprint $table) {
            $table->dropColumn(['customer_group_id', 'quantity', 'display_mode', 'priority']);
        });

        Schema::table('product_specials', function (Blueprint $table) {
            $table->renameColumn('price', 'value');
        });

        Schema::table('product_specials', function (Blueprint $table) {
            $table->string('price_type', 10)->default('fixed')->after('product_id');
            $table->dateTime('date_end')->nullable(false)->change();
        });
    }

    /** The old rows are split by what they can still do, so there is no way back. */
    public function down(): void
    {
        throw new RuntimeException('The split of product specials into specials and discounts cannot be undone.');
    }

    private function moveRows(): void
    {
        $now = now()->format('Y-m-d H:i:s');
        $rows = DB::table('product_specials')->orderBy('id')->get();

        $keep = [];
        $discounts = [];

        foreach ($rows as $row) {
            $ended = $row->date_end !== null && (string) $row->date_end < $now;
            if ($ended) {
                continue;
            }

            $standing = $row->display_mode === 'override' || $row->date_end === null || (int) $row->quantity > 1;
            if (! $standing) {
                $keep[] = $row->id;

                continue;
            }

            $key = $row->product_id . '|' . ($row->customer_group_id ?? 'all') . '|' . $row->quantity;
            $best = $discounts[$key] ?? null;
            if ($best === null || [$row->priority, (float) $row->price, $row->id] < [$best->priority, (float) $best->price, $best->id]) {
                $discounts[$key] = $row;
            }
        }

        foreach ($discounts as $row) {
            DB::table('product_discounts')->insert([
                'product_id' => $row->product_id,
                'customer_group_id' => $row->customer_group_id,
                'quantity' => max(1, (int) $row->quantity),
                'price_type' => 'fixed',
                'value' => $row->price,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }

        // Specials that stay are the only rows left; all others were moved or have ended.
        $remove = $rows->pluck('id')->diff($keep)->all();
        foreach (array_chunk($remove, 500) as $chunk) {
            DB::table('product_specials')->whereIn('id', $chunk)->delete();
        }
    }
};
