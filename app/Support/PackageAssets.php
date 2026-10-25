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
 * Only file types a browser loads as a stylesheet, script, font, raster
 * image or map are copied, and every part of a file's name is checked, not
 * only its last extension: some servers run x.php.css as PHP. SVG is not
 * copied, because an SVG opened on the store's own address can run script
 * there. The folder also gets an .htaccess that switches PHP off, for
 * servers that read one.
 */
final class PackageAssets
{
    public const PUBLIC_FOLDER = 'packages';

    private const NAME_PATTERN = '/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/';

    private const SERVED_TYPES = [
        'css', 'js', 'mjs', 'map', 'json',
        'woff', 'woff2', 'ttf', 'otf', 'eot',
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'ico',
    ];

    /** A part of a file name that a web server may run, or treat as its own settings. */
    private const RUNNABLE_PARTS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'pht', 'phps',
        'cgi', 'fcgi', 'pl', 'py', 'rb', 'sh', 'asp', 'aspx', 'jsp', 'shtml', 'shtm',
        'htaccess', 'htpasswd', 'ini', 'svg', 'svgz', 'html', 'htm', 'xhtml', 'xml',
    ];

    /** One folder or file name in build/: plain characters, never starting with a dot. */
    private const SEGMENT_PATTERN = '/^[A-Za-z0-9_-][A-Za-z0-9_.-]*$/';

    private const HTACCESS = <<<'CONF'
    # Written by VentaCart. Package builds are static files: nothing here runs.
    <IfModule mod_php.c>
        php_flag engine off
    </IfModule>
    <IfModule mod_mime.c>
        RemoveHandler .php .phtml .phar .pht .phps .cgi .pl .py .shtml
        RemoveType .php .phtml .phar .pht .phps
    </IfModule>
    Options -ExecCGI -Indexes
    <IfModule mod_headers.c>
        Header set X-Content-Type-Options "nosniff"
    </IfModule>

    CONF;

    /** The folder package builds are published to (public/packages). */
    public static function root(): string
    {
        return rtrim((string) (config('extensions.public_path') ?: public_path(self::PUBLIC_FOLDER)), '/');
    }

    /** Where a package's built files are served from. */
    public static function publicPath(string $name): string
    {
        return self::root() . '/' . $name;
    }

    /**
     * Whether a file at $relative inside build/ is one the store publishes:
     * every folder and file name plain, no part of the name one a server
     * could run, and a browser asset type at the end.
     */
    public static function servable(string $relative): bool
    {
        $segments = explode('/', str_replace('\\', '/', $relative));

        foreach ($segments as $segment) {
            if (! preg_match(self::SEGMENT_PATTERN, $segment)) {
                return false;
            }
        }

        $parts = array_map('strtolower', explode('.', (string) end($segments)));
        if (count($parts) < 2 || array_intersect($parts, self::RUNNABLE_PARTS) !== []) {
            return false;
        }

        return in_array(end($parts), self::SERVED_TYPES, true);
    }

    /**
     * Files in $packagePath/build that must never reach the public folder:
     * names a web server could run or read as its settings, and SVG. An
     * upload carrying one is refused rather than published in part.
     *
     * @return list<string>
     */
    public static function dangerousFiles(string $packagePath): array
    {
        $source = rtrim($packagePath, '/') . '/build';
        if (! is_dir($source) || is_link($source)) {
            return [];
        }

        $found = [];
        foreach (File::allFiles($source, true) as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            $parts = array_map('strtolower', explode('.', basename($relative)));
            $dotted = array_filter(explode('/', $relative), fn (string $segment) => str_starts_with($segment, '.'));

            if ($dotted !== [] || array_intersect(array_slice($parts, 1), self::RUNNABLE_PARTS) !== []) {
                $found[] = $relative;
            }
        }

        return $found;
    }

    /**
     * Copies $packagePath/build to the public folder under $name, replacing
     * any earlier copy. Returns false when the package brings no build.
     *
     * The new copy is built beside the old one and swapped in with two
     * renames, so a failure part way leaves the earlier copy in place.
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

        $root = self::root();
        File::ensureDirectoryExists($root, 0755);
        self::protect($root);

        $target = self::publicPath($name);
        $suffix = bin2hex(random_bytes(4));
        $staging = $root . '/.' . $name . '-' . $suffix;
        $aside = $root . '/.' . $name . '-old-' . $suffix;

        try {
            File::ensureDirectoryExists($staging, 0755);

            $skipped = [];
            foreach (File::allFiles($source, true) as $file) {
                $relative = str_replace('\\', '/', $file->getRelativePathname());

                if ($file->isLink() || ! self::servable($relative)) {
                    $skipped[] = $relative;

                    continue;
                }

                $to = $staging . '/' . $relative;
                File::ensureDirectoryExists(dirname($to), 0755);
                if (! @copy($file->getPathname(), $to)) {
                    throw new \RuntimeException("A built file of {$name} could not be published.");
                }
                @chmod($to, 0644);
            }

            if ($skipped !== []) {
                Log::warning("Package {$name}: files in build/ that are not browser assets were not published.", ['files' => array_slice($skipped, 0, 20)]);
            }

            if (is_dir($target) && ! @rename($target, $aside)) {
                throw new \RuntimeException("The published build of {$name} could not be moved aside.");
            }

            if (! @rename($staging, $target)) {
                if (is_dir($aside)) {
                    @rename($aside, $target);
                }

                throw new \RuntimeException("The build of {$name} could not be published.");
            }
        } finally {
            File::deleteDirectory($staging);
            File::deleteDirectory($aside);
        }

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

        foreach (glob(self::root() . '/*/manifest.json') ?: [] as $file) {
            $name = basename(dirname($file));
            $manifest = json_decode((string) @file_get_contents($file), true);

            if (preg_match(self::NAME_PATTERN, $name) && is_array($manifest)) {
                $found[$name] = $manifest;
            }
        }

        return $found;
    }

    /** Writes the .htaccess that keeps PHP off in the public package folder. */
    private static function protect(string $root): void
    {
        $file = $root . '/.htaccess';

        if (! is_file($file) || (string) @file_get_contents($file) !== self::HTACCESS) {
            @file_put_contents($file, self::HTACCESS);
        }
    }
}
