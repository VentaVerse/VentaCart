<?php

namespace App\Models\Concerns;

use App\Support\DateWindow;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * A model that applies between its date_start and date_end columns
 * (nullable DATETIME, cast to 'datetime'). The rules live in DateWindow;
 * this is where a model and its queries use them, so "is it on now" is
 * answered the same way in PHP and in SQL.
 *
 * Writes go through DateWindow too: a plain date written to date_end means
 * the end of that day, from any caller (admin form, import, seeder, API).
 */
trait HasDateWindow
{
    public function setDateStartAttribute(mixed $value): void
    {
        $start = DateWindow::start($value);
        $this->attributes['date_start'] = $start === null ? null : $this->fromDateTime($start);
    }

    public function setDateEndAttribute(mixed $value): void
    {
        $end = DateWindow::end($value);
        $this->attributes['date_end'] = $end === null ? null : $this->fromDateTime($end);
    }

    /** Whether this row applies at $at (default now). */
    public function isActiveAt(?CarbonInterface $at = null): bool
    {
        return DateWindow::contains($this->date_start, $this->date_end, $at);
    }

    /** Rows that apply at $at (default now). */
    public function scopeActiveAt(Builder $query, ?CarbonInterface $at = null): Builder
    {
        $moment = $this->fromDateTime(DateWindow::moment($at));
        $start = $query->qualifyColumn('date_start');
        $end = $query->qualifyColumn('date_end');

        return $query
            ->where(fn (Builder $q) => $q->whereNull($start)->orWhere($start, '<=', $moment))
            ->where(fn (Builder $q) => $q->whereNull($end)->orWhere($end, '>=', $moment));
    }
}
