<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * One-off repair for orders written before they owned their own history.
 *
 * Fresh installs are clean by default, because the model hooks on Order and
 * OrderHistory stamp every snapshot at write time, so this is a command and
 * not a migration, per the house rule.
 *
 * Safety contract, which the idempotency test pins:
 *   - only ever fills columns that are currently NULL;
 *   - only touches a row when the source actually yields a value, so a second
 *     run matches nothing and writes nothing;
 *   - never overwrites, never deletes, never resolves through anything but the
 *     foreign key the row already carries.
 *
 * The image backfill deserves its own note. The 2026-07-02 spec declined it on
 * the grounds that stamping today's photo onto an old order would be
 * "substituted history". That reasoning does not hold here: the order pages
 * already render products.image live for these rows, so freezing it changes
 * nothing a user can see. It only stops the value from being a live lookup
 * that a product delete can blank.
 */
class BackfillOrderSnapshots extends Command
{
    protected $signature = 'orders:backfill-snapshots {--chunk=2000 : Rows per id range} {--dry-run : Report what would be written without writing}';

    protected $description = 'Fill historical snapshot columns on orders, order histories and order lines from their existing foreign keys';

    public function handle(): int
    {
        $chunk = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Dry run: nothing will be written.');
        }

        // One implementation, shared with the migration that adds these
        // columns, so the automatic path and this repair tool can never drift.
        $totals = \App\Support\OrderSnapshotBackfill::run($chunk, $dryRun);

        $this->newLine();
        $this->table(
            ['Column', $dryRun ? 'Would write' : 'Rows written'],
            collect($totals)->map(fn (int $count, string $column) => [$column, number_format($count)])->values()->all(),
        );
        $this->info(($dryRun ? 'Would write ' : 'Wrote ') . number_format(array_sum($totals)) . ' values in total.');

        return self::SUCCESS;
    }

    /**
     * Fill one column across a table in id-ordered ranges.
     *
     * The correlated subquery is both the value and the filter: a row is only
     * matched when the subquery yields a non-null result, so affected-row
     * counts are exact on MySQL and SQLite alike, and a repeat run matches
     * nothing. Set-based per range, so there is no per-row query to N+1 on.
     */
    private function fill(string $table, string $column, string $subquery, int $chunk, bool $dryRun): int
    {
        $bounds = DB::table($table)->selectRaw('MIN(id) AS lo, MAX(id) AS hi')->first();

        if (! $bounds || $bounds->lo === null) {
            return 0;
        }

        $written = 0;
        $bar = $this->output->createProgressBar((int) ceil((($bounds->hi - $bounds->lo) + 1) / $chunk));
        $bar->setMessage("{$table}.{$column}");

        for ($low = (int) $bounds->lo; $low <= (int) $bounds->hi; $low += $chunk) {
            $high = $low + $chunk - 1;

            $pending = fn (): Builder => DB::table($table)
                ->whereBetween('id', [$low, $high])
                ->whereNull($column)
                ->whereRaw("{$subquery} IS NOT NULL");

            $written += $dryRun
                ? $pending()->count()
                : $pending()->update([$column => DB::raw($subquery)]);

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->line(sprintf('  %-38s %s', "{$table}.{$column}", number_format($written)));

        return $written;
    }
}
