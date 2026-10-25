<?php

namespace App\Support;

use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * A start and end moment that bound when something applies, such as a
 * product special.
 *
 * Both ends are stored as wall-clock datetimes in the store's time zone
 * (config('app.timezone'), set from the store settings at boot), and every
 * comparison is made against "now" in that same zone, so a sale entered as
 * ending 21:30 ends at 21:30 on the store's clock whatever zone the server
 * runs in.
 *
 * A value given as a plain date (YYYY-MM-DD) covers that whole day: a start
 * means 00:00:00 and an end means 23:59:59. Imports, integrations and older
 * records often carry only a date, and "ends October 31" has to include
 * October 31. A value with a time keeps that time, even when it is 00:00.
 * Both bounds are inclusive to the second.
 */
final class DateWindow
{
    private const DATE_ONLY = '/^\d{4}-\d{2}-\d{2}$/';

    /** The store's time zone, the one every window is written and read in. */
    public static function timezone(): string
    {
        return (string) config('app.timezone', 'UTC');
    }

    /**
     * The moment a window opens, from a date, a datetime or a date and time
     * value. A plain date opens at the start of that day.
     *
     * @throws \Carbon\Exceptions\InvalidFormatException when the value is not a date
     */
    public static function start(mixed $value): ?Carbon
    {
        return self::parse($value, endOfDay: false);
    }

    /**
     * The last moment a window applies. A plain date runs through the end
     * of that day.
     *
     * @throws \Carbon\Exceptions\InvalidFormatException when the value is not a date
     */
    public static function end(mixed $value): ?Carbon
    {
        return self::parse($value, endOfDay: true);
    }

    /**
     * Whether $at falls inside the window. A missing start or end leaves that
     * side open.
     */
    public static function contains(?CarbonInterface $start, ?CarbonInterface $end, ?CarbonInterface $at = null): bool
    {
        $at = self::moment($at);

        if ($start !== null && $start->greaterThan($at)) {
            return false;
        }

        if ($end !== null && $end->lessThan($at)) {
            return false;
        }

        return true;
    }

    /**
     * $at (default now) in the store's time zone, to the whole second. The
     * columns hold whole seconds, so dropping the fraction keeps an end of
     * 23:59:59 inclusive for the whole of that second, in PHP and in SQL
     * alike.
     */
    public static function moment(?CarbonInterface $at = null): Carbon
    {
        return Carbon::instance($at ?? now())
            ->setTimezone(self::timezone())
            ->startOfSecond();
    }

    /**
     * Date and time halves as one value for start() or end(): the date
     * alone when no time is given, so the whole day is covered.
     */
    public static function combine(?string $date, ?string $time): ?string
    {
        $date = trim((string) $date);
        $time = trim((string) $time);

        if ($date === '') {
            return null;
        }

        return $time === '' ? $date : $date . ' ' . $time;
    }

    private static function parse(mixed $value, bool $endOfDay): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        $zone = self::timezone();

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->setTimezone($zone)->startOfSecond();
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match(self::DATE_ONLY, $value)) {
            $day = Carbon::createFromFormat('!Y-m-d', $value, $zone);

            if ($day === null || $day->format('Y-m-d') !== $value) {
                throw new \Carbon\Exceptions\InvalidFormatException("Not a calendar date: {$value}");
            }

            return $endOfDay ? $day->setTime(23, 59, 59) : $day;
        }

        // A value that names its own offset (2026-10-31T21:30:00+08:00, or Z)
        // is that absolute moment, shown on the store's clock. One without an
        // offset is already store time.
        return Carbon::parse($value, $zone)->setTimezone($zone)->startOfSecond();
    }
}
