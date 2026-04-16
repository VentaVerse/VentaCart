<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Translates (preset + params) tuples into canonical cron expressions and
 * human-readable descriptions. Used by both the AutomationSeeder and the
 * admin edit flow — single source of truth for schedule shape.
 *
 * All methods are pure / stateless — safe to call from anywhere.
 */
class CronExpressionBuilder
{
    public const PRESETS = ['every_n_minutes', 'hourly', 'daily', 'weekly', 'monthly'];

    public const ALLOWED_MINUTE_INTERVALS = [1, 5, 10, 15, 20, 30];

    public const DAYS_OF_WEEK = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    /**
     * Build a cron expression string from a preset + params.
     *
     * @throws InvalidArgumentException on invalid preset or params
     */
    public static function build(string $preset, array $params): string
    {
        return match ($preset) {
            'every_n_minutes' => self::buildEveryNMinutes($params),
            'hourly' => self::buildHourly($params),
            'daily' => self::buildDaily($params),
            'weekly' => self::buildWeekly($params),
            'monthly' => self::buildMonthly($params),
            default => throw new InvalidArgumentException("Unknown schedule preset: {$preset}"),
        };
    }

    /**
     * Human-readable description of a preset + params tuple.
     * Example: "Daily at 08:00", "Every 15 minutes".
     */
    public static function describe(string $preset, array $params): string
    {
        return match ($preset) {
            'every_n_minutes' => self::describeEveryNMinutes($params),
            'hourly' => self::describeHourly($params),
            'daily' => self::describeDaily($params),
            'weekly' => self::describeWeekly($params),
            'monthly' => self::describeMonthly($params),
            default => throw new InvalidArgumentException("Unknown schedule preset: {$preset}"),
        };
    }

    // ──────────────────────────── every_n_minutes ────────────────────────────

    private static function buildEveryNMinutes(array $params): string
    {
        $n = self::requireInt($params, 'minute', 1, 59);
        if (!in_array($n, self::ALLOWED_MINUTE_INTERVALS, true)) {
            throw new InvalidArgumentException(
                "Minute interval must be one of: " . implode(', ', self::ALLOWED_MINUTE_INTERVALS)
            );
        }
        return $n === 1 ? '* * * * *' : "*/{$n} * * * *";
    }

    private static function describeEveryNMinutes(array $params): string
    {
        $n = (int) ($params['minute'] ?? 0);
        return $n === 1 ? 'Every minute' : "Every {$n} minutes";
    }

    // ──────────────────────────── hourly ────────────────────────────

    private static function buildHourly(array $params): string
    {
        $minute = self::requireInt($params, 'minute', 0, 59);
        return "{$minute} * * * *";
    }

    private static function describeHourly(array $params): string
    {
        $minute = (int) ($params['minute'] ?? 0);
        return 'Hourly at :' . str_pad((string) $minute, 2, '0', STR_PAD_LEFT);
    }

    // ──────────────────────────── daily ────────────────────────────

    private static function buildDaily(array $params): string
    {
        $hour = self::requireInt($params, 'hour', 0, 23);
        $minute = self::requireInt($params, 'minute', 0, 59);
        return "{$minute} {$hour} * * *";
    }

    private static function describeDaily(array $params): string
    {
        return 'Daily at ' . self::formatTime($params);
    }

    // ──────────────────────────── weekly ────────────────────────────

    private static function buildWeekly(array $params): string
    {
        $dow = self::requireInt($params, 'day_of_week', 0, 6);
        $hour = self::requireInt($params, 'hour', 0, 23);
        $minute = self::requireInt($params, 'minute', 0, 59);
        return "{$minute} {$hour} * * {$dow}";
    }

    private static function describeWeekly(array $params): string
    {
        $dow = (int) ($params['day_of_week'] ?? 0);
        $day = self::DAYS_OF_WEEK[$dow] ?? 'Sunday';
        return "Weekly on {$day} at " . self::formatTime($params);
    }

    // ──────────────────────────── monthly ────────────────────────────

    private static function buildMonthly(array $params): string
    {
        $dom = self::requireInt($params, 'day_of_month', 1, 28);
        $hour = self::requireInt($params, 'hour', 0, 23);
        $minute = self::requireInt($params, 'minute', 0, 59);
        return "{$minute} {$hour} {$dom} * *";
    }

    private static function describeMonthly(array $params): string
    {
        $dom = (int) ($params['day_of_month'] ?? 1);
        $suffix = self::ordinalSuffix($dom);
        return "Monthly on the {$dom}{$suffix} at " . self::formatTime($params);
    }

    // ──────────────────────────── helpers ────────────────────────────

    private static function requireInt(array $params, string $key, int $min, int $max): int
    {
        if (!array_key_exists($key, $params)) {
            throw new InvalidArgumentException("Missing required param: {$key}");
        }
        $value = $params[$key];
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new InvalidArgumentException("Param {$key} must be an integer, got: " . var_export($value, true));
        }
        $value = (int) $value;
        if ($value < $min || $value > $max) {
            throw new InvalidArgumentException("Param {$key} must be between {$min} and {$max}, got: {$value}");
        }
        return $value;
    }

    private static function formatTime(array $params): string
    {
        $hour = (int) ($params['hour'] ?? 0);
        $minute = (int) ($params['minute'] ?? 0);
        return str_pad((string) $hour, 2, '0', STR_PAD_LEFT) . ':'
            . str_pad((string) $minute, 2, '0', STR_PAD_LEFT);
    }

    private static function ordinalSuffix(int $n): string
    {
        if ($n >= 11 && $n <= 13) return 'th';
        return match ($n % 10) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };
    }
}
