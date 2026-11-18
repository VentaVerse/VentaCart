<?php

namespace App\Support\Media;

/**
 * Every place that stores a library image path, and what each one says
 * about a set of paths.
 *
 * Core registers its own columns at boot; a theme or extension registers the
 * places it keeps paths in its own service provider. Asking "who uses this
 * image" and moving an image then reach every one of them, so a file is
 * never deleted from under a menu banner or moved away from a theme setting
 * that nobody remembered to update.
 *
 * The sources live on the application container, so each application (and
 * each test) starts with exactly the registrations its providers made.
 */
final class MediaReferences
{
    /** @var array<int, MediaReferenceSource> */
    private array $sources = [];

    public static function register(MediaReferenceSource $source): void
    {
        self::instance()->sources[] = $source;
    }

    /** Forget every source. For tests that need a registry of their own. */
    public static function flush(): void
    {
        self::instance()->sources = [];
    }

    /**
     * How many of each kind of user point at each path. Only sources that
     * count as a use are asked; a kind with no users is left out.
     *
     * @param  array<int, string>  $paths
     * @return array<string, array<string, int>> path => [label => users]
     */
    public static function usage(array $paths): array
    {
        $keys = [];

        foreach (self::instance()->sources as $source) {
            if (! $source->countsAsUse()) {
                continue;
            }

            foreach ($source->owners($paths) as $path => $owners) {
                foreach ($owners as $owner) {
                    $keys[$path][$source->label()][$owner] = true;
                }
            }
        }

        $out = [];

        foreach ($paths as $path) {
            $out[$path] = array_map('count', $keys[$path] ?? []);
        }

        return $out;
    }

    /**
     * The total users of each path and, when they are all one kind, that
     * kind, so the admin can say "used by 3 products" rather than "used by 3".
     *
     * @param  array<int, string>  $paths
     * @return array<string, array{total: int, label: ?string}>
     */
    public static function summary(array $paths): array
    {
        return array_map(fn (array $kinds) => [
            'total' => array_sum($kinds),
            'label' => count($kinds) === 1 ? (string) array_key_first($kinds) : null,
        ], self::usage($paths));
    }

    /**
     * @param  array<int, string>  $paths
     * @return array<string, int> path => users
     */
    public static function inUse(array $paths): array
    {
        return array_map(fn (array $kinds) => array_sum($kinds), self::usage($paths));
    }

    /**
     * Point every stored copy of $from at $to, in every source, including
     * those that do not count as a use (an order line still shows its photo).
     * The caller owns the transaction.
     *
     * @return array<string, int> label => rows changed, kinds with none left out
     */
    public static function repoint(string $from, string $to): array
    {
        $out = [];

        foreach (self::instance()->sources as $source) {
            $changed = $source->repoint($from, $to);

            if ($changed > 0) {
                $out[$source->label()] = ($out[$source->label()] ?? 0) + $changed;
            }
        }

        return $out;
    }

    private static function instance(): self
    {
        $app = app();

        if (! $app->bound(self::class)) {
            $app->instance(self::class, new self());
        }

        return $app->make(self::class);
    }
}
