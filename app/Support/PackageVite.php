<?php

namespace App\Support;

use Illuminate\Foundation\Vite;

/**
 * The application's Vite, which also knows the builds packages bring with
 * them (see App\Support\PackageAssets).
 *
 * An entry is looked up in the store's own build first. A package whose
 * entries are all missing there has its manifest laid over it, with every
 * file pointed at public/packages/{name}. A package the store's build has
 * compiled is left out entirely, so the two builds never mix.
 */
class PackageVite extends Vite
{
    protected function manifest($buildDirectory)
    {
        $manifest = parent::manifest($buildDirectory);
        $packages = PackageAssets::manifests();

        if ($packages === []) {
            return $manifest;
        }

        $key = 'packages:' . $buildDirectory . ':' . md5(serialize($packages));

        return static::$manifests[$key] ??= $this->merge($manifest, $packages, $buildDirectory);
    }

    /**
     * @param  array<string, array<string, mixed>>  $manifest
     * @param  array<string, array<string, array<string, mixed>>>  $packages
     */
    private function merge(array $manifest, array $packages, string $buildDirectory): array
    {
        $up = str_repeat('../', substr_count(trim($buildDirectory, '/'), '/') + 1);

        foreach ($packages as $name => $chunks) {
            // A package's entries are its own source files only, so one
            // package's build can never stand in for another's entries or
            // for the store's.
            $own = self::sourcePattern((string) $name);
            $chunks = array_filter($chunks, fn ($chunk, $key) => str_starts_with((string) $key, '_') || preg_match($own, (string) $key), ARRAY_FILTER_USE_BOTH);
            $entries = array_filter(array_keys($chunks), fn ($key) => ! str_starts_with((string) $key, '_'));

            if ($entries === []) {
                continue;
            }

            // The store's own build already compiled this package.
            if (array_intersect($entries, array_keys($manifest)) !== []) {
                continue;
            }

            $prefix = $up . PackageAssets::PUBLIC_FOLDER . '/' . $name . '/';
            // Shared chunks are keyed by file name; keep each package's
            // apart from the store's and from every other package's.
            $rename = fn (string $key) => str_starts_with($key, '_') ? "_{$name}/" . substr($key, 1) : $key;

            foreach ($chunks as $key => $chunk) {
                if (! is_array($chunk) || ! isset($chunk['file'])) {
                    continue;
                }

                $chunk['file'] = $prefix . $chunk['file'];
                foreach (['css', 'assets'] as $list) {
                    if (isset($chunk[$list])) {
                        $chunk[$list] = array_map(fn ($file) => $prefix . $file, (array) $chunk[$list]);
                    }
                }
                foreach (['imports', 'dynamicImports'] as $list) {
                    if (isset($chunk[$list])) {
                        $chunk[$list] = array_values(array_map($rename, array_filter(
                            (array) $chunk[$list],
                            fn ($import) => is_string($import) && (str_starts_with($import, '_') || preg_match($own, $import)),
                        )));
                    }
                }

                $manifest[$rename((string) $key)] = $chunk;
            }
        }

        return $manifest;
    }

    /**
     * Where a package's own sources sit, as Vite keys them: a theme under
     * resources/themes/{slug}/, an extension under extensions/{name}/ or
     * inside the theme that brings it.
     */
    private static function sourcePattern(string $name): string
    {
        $quoted = preg_quote($name, '#');

        if (str_starts_with($name, 'theme-')) {
            return '#^resources/themes/' . preg_quote(substr($name, 6), '#') . '/#';
        }

        return '#^(?:extensions/' . $quoted . '/|resources/themes/[a-z0-9-]+/extensions/' . $quoted . '/)#';
    }

    protected function assetPath($path, $secure = null)
    {
        return parent::assetPath(self::withoutDotSegments((string) $path), $secure);
    }

    protected function publicPath($path)
    {
        return parent::publicPath(self::withoutDotSegments((string) $path));
    }

    /** build/../packages/x/a.css becomes packages/x/a.css. */
    private static function withoutDotSegments(string $path): string
    {
        if (! str_contains($path, '../')) {
            return $path;
        }

        $kept = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '..') {
                array_pop($kept);
            } elseif ($segment !== '.') {
                $kept[] = $segment;
            }
        }

        return implode('/', $kept);
    }
}
