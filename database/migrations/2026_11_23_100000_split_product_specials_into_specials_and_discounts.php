<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
 * date and would apply early. Where several rows land on the same product,
 * group and quantity, the one the cart used to charge is kept: lowest
 * priority number, then lowest price, then the earliest row.
 *
 * The console and the log list what a merchant should check: rows not moved
 * because they start later, sales that lost their dates, and every product
 * and group whose price for one item changes. The old rule charged the first
 * running row by priority even when it was above the regular price; the new
 * one charges the lowest of the regular price, the running specials and the
 * discounts.
 *
 * Each step checks what is already done, so a run that stopped part way can
 * be run again.
 */
return new class extends Migration
{
    /** @var list<string> What the move reported, kept so a test can read it. */
    public array $notes = [];

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

        // Both columns are needed to read the old rows; a store part way
        // through an earlier run may have lost customer_group_id already.
        if (Schema::hasColumn('product_specials', 'display_mode') && Schema::hasColumn('product_specials', 'customer_group_id')) {
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

        $oldRows = [];
        foreach (DB::table('product_specials')->orderBy('id')->get() as $row) {
            $oldRows[] = [
                'product_id' => $row->product_id, 'customer_group_id' => null, 'price' => $fixed($row),
                'quantity' => 1, 'display_mode' => 'sale', 'priority' => 0,
                'date_start' => $row->date_start, 'date_end' => $row->date_end,
                'created_at' => $row->created_at, 'updated_at' => $row->updated_at,
            ];
        }
        foreach (DB::table('product_discounts')->orderBy('id')->get() as $row) {
            $oldRows[] = [
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

        foreach (array_chunk($oldRows, 200) as $chunk) {
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
                $keep[] = (int) $row->id;

                continue;
            }

            if ($row->date_start !== null && (string) $row->date_start > $now) {
                $notes[] = 'not moved, starts later: ' . $this->describe($row);

                continue;
            }

            // A standing override never meant its dates as a sale period, so
            // only a sale losing its dates is worth a merchant's look.
            if ($row->display_mode === 'sale' && ($row->date_start !== null || $row->date_end !== null)) {
                $notes[] = 'dates dropped: ' . $this->describe($row);
            }

            $key = $row->product_id . '|' . ($row->customer_group_id ?? 'all') . '|' . max(1, (int) $row->quantity);
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

        $notes = array_merge($notes, $this->priceChanges($rows, $keep, $discounts, $now));

        foreach ($notes as $note) {
            $this->say($note);
        }
    }

    /**
     * One line per product and customer group whose price for one item is
     * not the same under the new rule as under the old one. Each group with
     * rows of its own is checked, and "all groups" stands for every buyer
     * whose group has none. Prices are the product's own; a variant moves by
     * the same amount.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $rows  every old row
     * @param  list<int>  $keep  ids of the rows that stay specials
     * @param  array<string, object>  $discounts  the old rows that became discounts
     * @return list<string>
     */
    private function priceChanges($rows, array $keep, array $discounts, string $now): array
    {
        $running = fn (object $row) => ($row->date_start === null || (string) $row->date_start <= $now)
            && ($row->date_end === null || (string) $row->date_end >= $now);

        $specials = $rows->filter(fn ($row) => in_array((int) $row->id, $keep, true) && $running($row));
        $products = DB::table('products')->whereIn('id', $rows->pluck('product_id')->unique()->all())->get(['id', 'sku', 'price'])->keyBy('id');
        $groups = DB::table('customer_groups')->pluck('name', 'id');

        $lines = [];
        foreach ($rows->groupBy('product_id') as $productId => $productRows) {
            $product = $products[$productId] ?? null;
            if ($product === null) {
                continue;
            }
            $regular = round((float) $product->price, 2);

            $groupIds = $productRows->pluck('customer_group_id')->filter(fn ($id) => $id !== null)->unique()->sort()->values()->all();
            foreach ([null, ...$groupIds] as $groupId) {
                $applies = fn (object $row) => $row->customer_group_id === null
                    || ($groupId !== null && (int) $row->customer_group_id === (int) $groupId);

                // The old rule: the first running row for one item by
                // priority, then price, then age, whatever its price.
                $old = $productRows
                    ->filter(fn ($row) => $applies($row) && $running($row) && (int) $row->quantity <= 1)
                    ->sort(fn ($a, $b) => [(int) $a->priority, (float) $a->price, (int) $a->id] <=> [(int) $b->priority, (float) $b->price, (int) $b->id])
                    ->first();
                $oldPrice = $old === null ? $regular : round((float) $old->price, 2);

                // The new rule: the lowest of the regular price, the running
                // specials and the discounts for one item.
                $candidates = [$regular];
                foreach ($specials->where('product_id', $productId) as $row) {
                    $candidates[] = round((float) $row->price, 2);
                }
                foreach ($discounts as $row) {
                    if ((int) $row->product_id === (int) $productId && $applies($row) && max(1, (int) $row->quantity) <= 1) {
                        $candidates[] = round((float) $row->price, 2);
                    }
                }
                $newPrice = min($candidates);

                if ((int) round($oldPrice * 100) !== (int) round($newPrice * 100)) {
                    $lines[] = sprintf(
                        'price changes: %s, %s, %s -> %s',
                        $product->sku,
                        $groupId === null ? 'all groups' : ($groups[$groupId] ?? 'group ' . $groupId),
                        number_format($oldPrice, 2, '.', ''),
                        number_format($newPrice, 2, '.', ''),
                    );
                }
            }
        }

        return $lines;
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

    /**
     * A line for the merchant, on the console and in the log so it outlives
     * the terminal. Silent in tests, which read $notes instead.
     */
    private function say(string $line): void
    {
        $this->notes[] = $line;

        if (app()->runningUnitTests()) {
            return;
        }

        Log::warning('Splitting product specials: ' . $line);
        if (app()->runningInConsole()) {
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
