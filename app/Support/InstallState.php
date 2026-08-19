<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Is this database a brand new Venta, or a store that has been running?
 *
 * The continuity migrations exist to carry an EXISTING store's choices into the
 * extension registry. On a database that has never been a store there is
 * nothing to carry, and anything they write is a default nobody picked. So they
 * all need to answer this question, and every answer they used before was
 * wrong.
 *
 * Each of them tested for the extension's own table (`hasTable('blog_posts')`,
 * `hasTable('bank_accounts')`), reasoning that a store which had the feature
 * would have its table. But core migrations create those same tables on a fresh
 * install, seconds earlier in the same run, so the test said "existing store"
 * to a database that was brand new. The `settings` table is no better: twelve
 * rows arrive from migrations themselves, `payment.bank_transfer_instructions`
 * among them, which is why the payment continuity's legacy check also fired on
 * a fresh install. Nor is row-counting orders or products, because a real shop
 * can legitimately have neither on the day it opens.
 *
 * The migration BATCH is the one signal a fresh database cannot fake. Laravel
 * stamps every migration of a single run with the same batch number, so a fresh
 * install is entirely batch 1, while a store that has migrated before is on at
 * least batch 2 by the time these run. The migrator records each migration
 * after its up() completes, so by the time any continuity migration is reached,
 * the earlier migrations of this same run have already been stamped and the
 * maximum is this run's own batch.
 */
class InstallState
{
    /**
     * True when the database is being built for the first time.
     *
     * Conservative on purpose: anything unexpected (no migrations table yet,
     * an empty one) reads as a fresh install, because the cost of skipping
     * continuity on a store that has none is nothing, while the cost of
     * running it on a genuinely fresh install is a default nobody chose.
     */
    public static function isFreshInstall(): bool
    {
        if (! Schema::hasTable('migrations')) {
            return true;
        }

        return (int) DB::table('migrations')->max('batch') <= 1;
    }
}
