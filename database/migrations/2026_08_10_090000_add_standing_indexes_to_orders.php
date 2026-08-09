<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The day sheet leads with standing, and standing is a question about a
 * WINDOW of orders rather than about one order. Three schema changes make
 * those windows seekable on a store carrying a decade of trade.
 *
 * 1. orders_store_created_money_index (store_id, created_at,
 *    order_status_id, total)
 *
 *    The pulse totals revenue and order count by month across two years, so
 *    every column the aggregate touches is in this index and MySQL never
 *    leaves it. Measured on the development database (27,709 orders, 8,192
 *    of them inside the window): 105 ms reading rows, 24 ms reading only the
 *    index.
 *
 * 2. orders_store_id_foreign is DROPPED.
 *
 *    This is the change that actually made (1) work. Laravel creates a
 *    single-column index for every foreign key, so orders carried both
 *    (store_id) and (store_id, created_at). A deployment runs one store, so
 *    store_id has one value, and MySQL still preferred the single-column
 *    `ref` lookup over a `range` seek on any composite: it read 11,219 rows
 *    and sorted them, every time, whatever composite index it was offered.
 *    With the redundant index gone the optimiser picks the covering index on
 *    its own and no query in the application needs an index hint.
 *
 *    The foreign key itself is untouched. InnoDB requires an index whose
 *    LEFTMOST column is the referencing column, and orders_store_created_index
 *    is exactly that, which is why the drop is accepted. The guard below
 *    refuses to drop anything if that composite is not present.
 *
 * 3. orders_email_store_created_index (email, store_id, created_at)
 *
 *    New versus returning buyers asks, per buyer seen in the period, whether
 *    that buyer has an earlier order. Without this index the answer took
 *    1,310 ms; with it, 5 ms, because each buyer is one index seek that stops
 *    at the first row it finds.
 *
 *    EMAIL LEADS, AND THAT ORDER IS LOAD BEARING. Written the obvious way
 *    round, as (store_id, email, created_at), it walked straight back into
 *    the trap that (2) exists to close: MySQL took it as another way to do a
 *    `ref` lookup on store_id alone and the monthly aggregate went from 23 ms
 *    to 76 ms. Leading on the high-cardinality column serves the buyer
 *    lookup exactly as well and offers the optimiser nothing it can misuse.
 *    Any future index on orders that leads with store_id must be checked
 *    against the monthly aggregate before it ships.
 *
 * Nothing here reads, writes or moves a single row of data. Both directions
 * are index-only, so a rollback costs the same as the migration.
 */
return new class extends Migration
{
    /** The composite that must exist before the redundant one may go. */
    private const FK_COVER = 'orders_store_created_index';

    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! $this->hasIndex('orders_store_created_money_index')) {
                $table->index(
                    ['store_id', 'created_at', 'order_status_id', 'total'],
                    'orders_store_created_money_index'
                );
            }

            if (! $this->hasIndex('orders_email_store_created_index')) {
                $table->index(
                    ['email', 'store_id', 'created_at'],
                    'orders_email_store_created_index'
                );
            }
        });

        // Only MySQL grows a duplicate index per foreign key, and only MySQL
        // needs it gone. SQLite names no such index at all, so asking is
        // cheaper than guessing.
        if ($this->hasIndex(self::FK_COVER) && $this->hasIndex('orders_store_id_foreign')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropIndex('orders_store_id_foreign');
            });
        }
    }

    public function down(): void
    {
        if (! $this->hasIndex('orders_store_id_foreign')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->index('store_id', 'orders_store_id_foreign');
            });
        }

        Schema::table('orders', function (Blueprint $table) {
            if ($this->hasIndex('orders_store_created_money_index')) {
                $table->dropIndex('orders_store_created_money_index');
            }

            if ($this->hasIndex('orders_email_store_created_index')) {
                $table->dropIndex('orders_email_store_created_index');
            }
        });
    }

    /**
     * Schema::hasIndex is the portable question; it is asked through a try
     * because a connection that cannot introspect indexes should leave the
     * schema alone rather than fail the deploy.
     */
    private function hasIndex(string $name): bool
    {
        try {
            return Schema::hasIndex('orders', $name);
        } catch (Throwable $e) {
            return false;
        }
    }
};
