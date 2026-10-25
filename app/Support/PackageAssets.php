<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Built stylesheets and scripts a package brings with it.
 *
 * A theme or extension sold as a download cannot ask the store to run the
 * front-end build: most stores have no build tools on the server. Such a
 * package ships its compiled files in a build/ folder (a Vite manifest.json
 * beside the files it names). Installing or updating the package copies
 * that folder to public/packages/{name}, and App\Support\PackageVite serves
 * an entry from it whenever the store's own build does not have the entry.
 * A store that does build its front end compiles the package with
 * everything else, and its own build wins.
 *
 * Only file types a browser loads as a stylesheet, script, font, image or
 * map are copied, so nothing the web server would run as code ever lands in
 * the public folder.
 */
final class PackageAssets
{
    public const PUBLIC_FOLDER = 'packages';

    private const NAME_PATTERN = '/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/';

    private const SERVED_TYPES = [
        'css', 'js', 'mjs', 'map', 'json',
        'woff', 'woff2', 'ttf', 'otf', 'eot',
        'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'ico',
    ];

    /** Where a package's built files are served from. */
    public static function publicPath(string $name): string
    {
        return public_path(self::PUBLIC_FOLDER . '/' . $name);
    }

    /**
     * Copies $packagePath/build to the public folder under $name, replacing
     * any earlier copy. Returns false when the package brings no build.
     */
    public static function publish(string $name, string $packagePath): bool
    {
        if (! preg_match(self::NAME_PATTERN, $name)) {
            return false;
        }

        $source = rtrim($packagePath, '/') . '/build';

        if (! is_file($source . '/manifest.json') || is_link($source)) {
            self::remove($name);

            return false;
        }

        $target = self::publicPath($name);
        $staging = public_path(self::PUBLIC_FOLDER . '/.' . $name . '-' . bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($staging, 0755);

        $skipped = [];
        foreach (File::allFiles($source) as $file) {
            if ($file->isLink() || ! in_array(strtolower($file->getExtension()), self::SERVED_TYPES, true)) {
                $skipped[] = $file->getRelativePathname();

                continue;
            }

            $to = $staging . '/' . $file->getRelativePathname();
            File::ensureDirectoryExists(dirname($to), 0755);
            File::copy($file->getPathname(), $to);
            @chmod($to, 0644);
        }

        if ($skipped !== []) {
            Log::warning("Package {$name}: files in build/ that are not browser assets were not published.", ['files' => array_slice($skipped, 0, 20)]);
        }

        File::deleteDirectory($target);
        rename($staging, $target);

        return true;
    }

    /** Removes a package's published build. */
    public static function remove(string $name): void
    {
        if (preg_match(self::NAME_PATTERN, $name)) {
            File::deleteDirectory(self::publicPath($name));
        }
    }

    /**
     * Every published build's manifest, by package name.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public static function manifests(): array
    {
        $found = [];

        foreach (glob(public_path(self::PUBLIC_FOLDER . '/*/manifest.json')) ?: [] as $file) {
            $name = basename(dirname($file));
            $manifest = json_decode((string) @file_get_contents($file), true);

            if (preg_match(self::NAME_PATTERN, $name) && is_array($manifest)) {
                $found[$name] = $manifest;
            }
        }

        return $found;
    }
}
