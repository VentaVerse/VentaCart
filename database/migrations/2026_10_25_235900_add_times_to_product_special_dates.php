<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Product specials start and end at a time of day, not just on a date.
 *
 * date_start and date_end become nullable DATETIME columns. A date already
 * stored keeps its meaning as a whole day: a start becomes 00:00:00 of that
 * day and an end becomes 23:59:59, so a sale that ended "October 31" still
 * runs through October 31 (a DATE end compared against the current time
 * used to stop at midnight at the start of that day).
 *
 * Each column is converted only while it is still a DATE, so a fresh
 * install (no rows) and a store that already has the new type are both
 * left as they are.
 */
return new class extends Migration
{
    private const TABLE = 'product_specials';

    public function up(): void
    {
        $convertStart = $this->isDateColumn('date_start');
        $convertEnd = $this->isDateColumn('date_end');

        if (! $convertStart && ! $convertEnd) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) use ($convertStart, $convertEnd) {
            if ($convertStart) {
                $table->dateTime('date_start')->nullable()->change();
            }
            if ($convertEnd) {
                $table->dateTime('date_end')->nullable()->change();
            }
        });

        DB::transaction(function () use ($convertStart, $convertEnd) {
            if ($convertStart) {
                $this->setTimeOfDay('date_start', '00:00:00');
            }
            if ($convertEnd) {
                $this->setTimeOfDay('date_end', '23:59:59');
            }
        });
    }

    /**
     * Back to DATE columns. The time of day is dropped, so an end of 21:30
     * reads as the whole of that day's date again.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        foreach (['date_start', 'date_end'] as $column) {
            if ($this->isDateColumn($column)) {
                continue;
            }

            $day = match ($driver) {
                'pgsql' => "CAST({$column} AS DATE)",
                default => "DATE({$column})",
            };

            DB::table(self::TABLE)->whereNotNull($column)->update([$column => DB::raw($day)]);

            Schema::table(self::TABLE, function (Blueprint $table) use ($column) {
                $table->date($column)->nullable()->change();
            });
        }
    }

    private function isDateColumn(string $column): bool
    {
        return strtolower(Schema::getColumnType(self::TABLE, $column)) === 'date';
    }

    /** Sets every stored value in $column to its own day at $time. */
    private function setTimeOfDay(string $column, string $time): void
    {
        $value = match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => "TIMESTAMP(DATE({$column}), '{$time}')",
            'pgsql' => "DATE_TRUNC('day', {$column}) + INTERVAL '{$time}'",
            default => "DATE({$column}) || ' {$time}'",
        };

        DB::table(self::TABLE)->whereNotNull($column)->update([$column => DB::raw($value)]);
    }
};
