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
 * always on moves to product_discounts, for one customer group or all of
 * them.
 *
 * The move never widens who gets a price. Rows whose end has passed are
 * dropped. A "sale" row with no group and an end date stays a special with
 * its dates. Every other running row (standing "override" rows, undated
 * sales, sales for one group, rows from a quantity above one) becomes a
 * discount for its group, losing its dates. A row that has not started yet
 * and would become a discount is not moved, because a discount has no start
 * date and would apply early; it is listed on the console so it can be
 * entered again. Where several rows land on the same product, group and
 * quantity, the one the cart used to charge is kept: lowest priority number,
 * then lowest price, then the earliest row.
 *
 * Each step checks what is already done, so a run that stopped part way can
 * be run again.
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

        if (Schema::hasColumn('product_specials', 'display_mode')) {
            DB::transaction(function () {
                $this->moveRows();
            });
        }

        // The new index goes in first: MySQL needs one that starts with
        // product_id for its foreign key while the old ones are dropped.
        if (! Schema::hasIndex('product_specials', 'ps_product_dates_index')) {
            Schema::table('product_specials', function (Blueprint $table) {
                $table->index(['product_id', 'date_start', 'date_end'], 'ps_product_dates_index');
            });
        }

        if (Schema::hasIndex('product_specials', 'ps_product_display_dates_index')) {
            Schema::table('product_specials', function (Blueprint $table) {
                $table->dropIndex('ps_product_display_dates_index');
            });
        }
        if (Schema::hasIndex('product_specials', ['product_id', 'priority'])) {
            Schema::table('product_specials', function (Blueprint $table) {
                $table->dropIndex(['product_id', 'priority']);
            });
        }

        // The foreign key goes before its column, which MySQL requires.
        if (Schema::hasColumn('product_specials', 'customer_group_id')) {
            if ($this->hasForeignKey('product_specials', 'customer_group_id')) {
                Schema::table('product_specials', function (Blueprint $table) {
                    $table->dropForeign(['customer_group_id']);
                });
            }
            Schema::table('product_specials', function (Blueprint $table) {
                $table->dropColumn('customer_group_id');
            });
        }

        $obsolete = array_values(array_filter(
            ['quantity', 'display_mode', 'priority'],
            fn (string $column) => Schema::hasColumn('product_specials', $column),
        ));
        if ($obsolete !== []) {
            Schema::table('product_specials', function (Blueprint $table) use ($obsolete) {
                $table->dropColumn($obsolete);
            });
        }

        if (Schema::hasColumn('product_specials', 'price')) {
            Schema::table('product_specials', function (Blueprint $table) {
                $table->renameColumn('price', 'value');
            });
        }

        if (! Schema::hasColumn('product_specials', 'price_type')) {
            Schema::table('product_specials', function (Blueprint $table) {
                $table->string('price_type', 10)->default('fixed')->after('product_id');
            });
        }

        Schema::table('product_specials', function (Blueprint $table) {
            $table->decimal('value', 15, 2)->change();
            $table->dateTime('date_end')->nullable(false)->change();
        });
    }

    /**
     * Back to one table. Specials return as sales for every group, discounts
     * as standing overrides, and a percentage is turned into a fixed price
     * from the product's current price, as the old table had no percentages.
     */
    public function down(): void
    {
        $products = DB::table('products')->pluck('price', 'id');
        $fixed = fn (object $row) => $row->price_type === 'percent'
            ? round((float) ($products[$row->product_id] ?? 0) * (1 - (float) $row->value / 100), 2)
            : $row->value;

        $legacy = [];
        foreach (DB::table('product_specials')->orderBy('id')->get() as $row) {
            $legacy[] = [
                'product_id' => $row->product_id, 'customer_group_id' => null, 'price' => $fixed($row),
                'quantity' => 1, 'display_mode' => 'sale', 'priority' => 0,
                'date_start' => $row->date_start, 'date_end' => $row->date_end,
                'created_at' => $row->created_at, 'updated_at' => $row->updated_at,
            ];
        }
        foreach (DB::table('product_discounts')->orderBy('id')->get() as $row) {
            $legacy[] = [
                'product_id' => $row->product_id, 'customer_group_id' => $row->customer_group_id, 'price' => $fixed($row),
                'quantity' => $row->quantity, 'display_mode' => 'override', 'priority' => 0,
                'date_start' => null, 'date_end' => null,
                'created_at' => $row->created_at, 'updated_at' => $row->updated_at,
            ];
        }

        DB::table('product_specials')->delete();

        Schema::table('product_specials', function (Blueprint $table) {
            $table->dropColumn('price_type');
            $table->renameColumn('value', 'price');
        });
        Schema::table('product_specials', function (Blueprint $table) {
            $table->decimal('price', 12, 2)->change();
            $table->dateTime('date_end')->nullable()->change();
            $table->foreignId('customer_group_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
            $table->integer('quantity')->default(1)->after('price');
            $table->enum('display_mode', ['override', 'sale'])->default('sale');
            $table->integer('priority')->default(0);
        });
        // An index that starts with product_id must exist at all times (MySQL
        // needs it for the foreign key), so the old one goes in before the new one goes.
        Schema::table('product_specials', function (Blueprint $table) {
            $table->index(['product_id', 'priority']);
        });
        Schema::table('product_specials', function (Blueprint $table) {
            $table->dropIndex('ps_product_dates_index');
            $table->index(['product_id', 'display_mode', 'date_start', 'date_end'], 'ps_product_display_dates_index');
        });

        foreach (array_chunk($legacy, 200) as $chunk) {
            DB::table('product_specials')->insert($chunk);
        }

        Schema::dropIfExists('product_discounts');
    }

    private function moveRows(): void
    {
        // Dates are stored as store-time 'Y-m-d H:i:s', so strings compare in order.
        $now = now()->format('Y-m-d H:i:s');
        $rows = DB::table('product_specials')->orderBy('id')->get();

        $keep = [];
        $discounts = [];
        $notes = [];

        foreach ($rows as $row) {
            if ($row->date_end !== null && (string) $row->date_end < $now) {
                continue;
            }

            $stays = $row->display_mode === 'sale' && $row->customer_group_id === null
                && $row->date_end !== null && (int) $row->quantity <= 1;
            if ($stays) {
                $keep[] = $row->id;

                continue;
            }

            if ($row->date_start !== null && (string) $row->date_start > $now) {
                $notes[] = 'not moved, starts later: ' . $this->describe($row);

                continue;
            }

            if ($row->date_start !== null || $row->date_end !== null) {
                $notes[] = 'dates dropped: ' . $this->describe($row);
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

        // Specials that stay are the only rows left; all others were moved, skipped or have ended.
        $remove = $rows->pluck('id')->diff($keep)->all();
        foreach (array_chunk($remove, 500) as $chunk) {
            DB::table('product_specials')->whereIn('id', $chunk)->delete();
        }

        foreach ($notes as $note) {
            $this->say($note);
        }
    }

    private function describe(object $row): string
    {
        $sku = DB::table('products')->where('id', $row->product_id)->value('sku');
        $group = $row->customer_group_id === null
            ? 'all groups'
            : DB::table('customer_groups')->where('id', $row->customer_group_id)->value('name');

        return sprintf(
            '%s, %s, price %s, quantity %d, from %s to %s',
            $sku, $group, $row->price, $row->quantity, $row->date_start ?? 'none', $row->date_end ?? 'none',
        );
    }

    /** Console output for the merchant; silent in tests and web requests. */
    private function say(string $line): void
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            fwrite(STDOUT, '  ' . $line . PHP_EOL);
        }
    }

    private function hasForeignKey(string $table, string $column): bool
    {
        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if (in_array($column, $foreignKey['columns'], true)) {
                return true;
            }
        }

        return false;
    }
};
