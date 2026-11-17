<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Pages extensions add to the store's XML sitemap, such as a seller's shop
 * at /shop/{slug}.
 *
 * A callable yields entries (a generator suits a large set, since entries
 * are read one at a time and never kept), each checked before it reaches
 * the sitemap: the address must be a page of this store, the optional
 * values must be what the sitemap format allows, and an address already
 * listed is listed once. An entry that fails a check is skipped. A
 * callable that throws is reported and stops there; the sitemap is still
 * written with everything else.
 */
final class SitemapUrls
{
    /** The values the sitemap format allows for changefreq. */
    public const CHANGE_FREQUENCIES = ['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'];

    /** The longest address the sitemap format allows. */
    public const MAX_LENGTH = 2048;

    private const NAME = '/^[a-z][a-z0-9-]{0,39}$/';

    /** @var array<string, callable> by name, in the order added */
    private static array $sources = [];

    /**
     * From an extension's service provider.
     *
     * @param  string  $name  the extension's name, or another short name of its own (lower case letters, digits and hyphens)
     * @param  callable(): iterable<array{loc: string, lastmod?: \DateTimeInterface|string|null, changefreq?: string|null, priority?: string|float|int|null}>  $fn
     */
    public static function add(string $name, callable $fn): void
    {
        if (preg_match(self::NAME, $name) !== 1) {
            Log::warning('A sitemap source was refused: its name must be lower case letters, digits and hyphens.', ['name' => $name]);

            return;
        }
        if (isset(self::$sources[$name])) {
            Log::warning('A sitemap source was refused: the name is taken.', ['name' => $name]);

            return;
        }

        self::$sources[$name] = $fn;
    }

    public static function has(): bool
    {
        return self::$sources !== [];
    }

    public static function forget(): void
    {
        self::$sources = [];
    }

    /**
     * Every entry the extensions give, checked, one at a time.
     *
     * @param  array<string, mixed>  $listed  addresses the sitemap already has (core's own pages), as keys; never listed again
     * @return \Generator<int, array{loc: string, lastmod: ?string, changefreq: ?string, priority: ?string}>
     */
    public static function all(array $listed = []): \Generator
    {
        if (! self::has()) {
            return;
        }

        $base = self::base();
        if ($base === null) {
            Log::warning('Sitemap entries from extensions were left out: the store address (APP_URL) is not a web address.');

            return;
        }

        // The addresses yielded so far, as short hashes, so a large set
        // costs a fixed few bytes per address.
        $seen = [];
        foreach (self::$sources as $name => $fn) {
            $skipped = 0;
            try {
                foreach ($fn() as $entry) {
                    $clean = self::check($entry, $base);
                    if ($clean === null) {
                        $skipped++;
                        continue;
                    }
                    $key = md5($clean['loc'], true);
                    if (isset($listed[$clean['loc']]) || isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;

                    yield $clean;
                }
            } catch (\Throwable $e) {
                report($e);
            }
            if ($skipped > 0) {
                Log::warning('Sitemap entries from an extension were skipped: not a page of this store, or a value the sitemap does not allow.', ['source' => $name, 'skipped' => $skipped]);
            }
        }
    }

    /**
     * One entry made safe for the sitemap, or null when it cannot be.
     *
     * @param  array{scheme: string, host: string, port: ?int}  $base
     * @return array{loc: string, lastmod: ?string, changefreq: ?string, priority: ?string}|null
     */
    private static function check(mixed $entry, array $base): ?array
    {
        if (! is_array($entry) || ! is_string($entry['loc'] ?? null)) {
            return null;
        }

        $loc = trim($entry['loc']);
        if ($loc === '' || strlen($loc) > self::MAX_LENGTH || filter_var($loc, FILTER_VALIDATE_URL) === false) {
            return null;
        }
        $parts = parse_url($loc);
        if ($parts === false
            || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || strtolower($parts['host'] ?? '') !== $base['host']
            || self::port($parts) !== $base['port']
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            return null;
        }

        $changefreq = $entry['changefreq'] ?? null;
        if ($changefreq !== null && ! in_array($changefreq, self::CHANGE_FREQUENCIES, true)) {
            return null;
        }

        $priority = $entry['priority'] ?? null;
        if ($priority !== null) {
            if (! is_int($priority) && ! is_float($priority) && ! (is_string($priority) && is_numeric(trim($priority)))) {
                return null;
            }
            $value = round(max(0.0, min(1.0, (float) $priority)), 2);
            if (is_nan($value)) {
                return null;
            }
            $priority = number_format($value, round($value, 1) === $value ? 1 : 2, '.', '');
        }

        $lastmod = $entry['lastmod'] ?? null;
        if ($lastmod !== null) {
            $lastmod = self::date($lastmod);
            if ($lastmod === null) {
                return null;
            }
        }

        return ['loc' => $loc, 'lastmod' => $lastmod, 'changefreq' => $changefreq, 'priority' => $priority];
    }

    /** A W3C date (2026-01-31T09:30:00+08:00), or null for anything that is not a date. */
    private static function date(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value)->toW3cString();
        }
        if (! is_string($value) || trim($value) === '' || strlen($value) > 64) {
            return null;
        }
        try {
            return CarbonImmutable::parse(trim($value))->toW3cString();
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{scheme: string, host: string, port: ?int}|null */
    private static function base(): ?array
    {
        $parts = parse_url((string) config('app.url'));
        if (! is_array($parts) || empty($parts['host']) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return null;
        }

        return ['scheme' => strtolower($parts['scheme']), 'host' => strtolower($parts['host']), 'port' => self::port($parts)];
    }

    /**
     * The port an address names, with the scheme's own port read as none,
     * so https://example.com and https://example.com:443 are one store.
     *
     * @param  array<string, mixed>  $parts
     */
    private static function port(array $parts): ?int
    {
        $port = isset($parts['port']) ? (int) $parts['port'] : null;
        $default = strtolower((string) ($parts['scheme'] ?? '')) === 'https' ? 443 : 80;

        return $port === $default ? null : $port;
    }
}
