<?php

namespace App\Support;

use App\Exceptions\PackageRefused;
use ZipArchive;

/**
 * An uploaded package archive (a theme or an extension as a .zip), read and
 * checked before a single file of it reaches the disk.
 *
 * The archive must hold one folder named after the package, with its
 * theme.json or extension.json directly inside. Every entry is checked from
 * the archive's own directory: no absolute paths, no "..", no links or
 * device files, no file over the size limit, no more files than the limit
 * allows. Extraction then writes each file itself, counting bytes, so an
 * entry that claims to be small and inflates to more is stopped too.
 *
 * Nothing in the archive is run, included or rendered here: manifests are
 * read as JSON and every other file is copied as bytes.
 */
final class PackageArchive
{
    /** A package slug: lowercase letters and digits, words joined by single hyphens. */
    public const SLUG_PATTERN = '/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/';

    public const SLUG_MAX = 64;

    /** A version the registry can compare: 1.2, 1.2.3 or 1.2.3-beta.1. */
    public const VERSION_PATTERN = '/^\d+(?:\.\d+){1,3}(?:[-+][0-9A-Za-z.-]+)?$/';

    /** The longest version a package may give (package_uploads.version holds 40). */
    public const VERSION_MAX = 40;

    /** The longest name of a package shown to an admin or written to the log. */
    public const TITLE_MAX = 120;

    /** Namespaces of the application, the framework and the tools that build them. */
    private const RESERVED_NAMESPACES = ['App\\', 'Illuminate\\', 'Laravel\\', 'Symfony\\', 'Database\\', 'Tests\\', 'Composer\\'];

    /** Names Windows keeps for devices, which no file of a package may take. */
    private const DEVICE_NAMES = '/^(?:con|prn|aux|nul|com[0-9]|lpt[0-9])(?:\..*)?$/i';

    /** Entries zip tools add on their own, skipped rather than refused. */
    private const SKIPPED = ['__MACOSX/', '.DS_Store', 'Thumbs.db'];

    /** Unix file type bits as zip archives store them (the high 16 bits). */
    private const TYPE_MASK = 0xF000;

    private const TYPE_FILE = 0x8000;

    private const TYPE_DIR = 0x4000;

    private const TYPE_LINK = 0xA000;

    private const MANIFEST_MAX_BYTES = 1048576;

    /** @var list<array{index: int, name: string, dir: bool, size: int}> */
    private array $entries = [];

    /** @var array<string, array<string, mixed>>|null */
    private ?array $bundled = null;

    private function __construct(
        private ZipArchive $zip,
        private string $slug,
        private string $kind,
        private array $manifest,
    ) {
    }

    /**
     * Opens and checks the archive at $file.
     *
     * @param  array{max_files: int, max_file_bytes: int, max_total_bytes: int}  $limits
     *
     * @throws PackageRefused
     */
    public static function open(string $file, array $limits): self
    {
        $zip = new ZipArchive();

        if ($zip->open($file, ZipArchive::RDONLY) !== true) {
            throw new PackageRefused('This file is not a zip archive, or it is damaged.');
        }

        $count = $zip->count();
        if ($count === 0) {
            throw new PackageRefused('The archive is empty.');
        }
        if ($count > $limits['max_files']) {
            throw new PackageRefused("The archive holds {$count} entries; a package may hold at most {$limits['max_files']}.");
        }

        $entries = [];
        $seen = [];
        $folds = [];
        $tops = [];
        $total = 0;

        for ($i = 0; $i < $count; $i++) {
            $stat = $zip->statIndex($i, ZipArchive::FL_UNCHANGED);
            if ($stat === false) {
                throw new PackageRefused('The archive is damaged: an entry cannot be read.');
            }

            $name = (string) $stat['name'];

            if (self::skipped($name)) {
                continue;
            }

            // zip tools that do not mark names as UTF-8 get them read as an
            // old DOS code page, which turns control bytes into printable
            // symbols. The bytes as stored are checked too.
            $raw = $zip->getNameIndex($i, ZipArchive::FL_ENC_RAW | ZipArchive::FL_UNCHANGED);
            if (is_string($raw) && preg_match('/[\x00-\x1F\x7F\\\\]/', $raw)) {
                self::assertSafeName($raw);
            }

            self::assertSafeName($name);

            if (($stat['encryption_method'] ?? 0) !== 0) {
                throw new PackageRefused('The archive is password protected (' . self::plain($name, 200) . '). Upload it without a password.');
            }

            $dir = str_ends_with($name, '/');
            $type = self::unixType($zip, $i);

            if ($type === self::TYPE_LINK) {
                throw new PackageRefused("The archive holds a link ({$name}). A package holds plain files and folders only.");
            }
            if ($type !== null && $type !== self::TYPE_FILE && $type !== self::TYPE_DIR) {
                throw new PackageRefused("The archive holds something that is not a file or a folder ({$name}).");
            }
            if ($type === self::TYPE_DIR && ! $dir) {
                $dir = true;
                $name .= '/';
            }

            $key = rtrim($name, '/');
            // Case-insensitive file systems (Windows, macOS) would write two
            // names that differ only in case to the same file.
            $folded = mb_strtolower($key);
            if (isset($seen[$key]) || isset($folds[$folded])) {
                throw new PackageRefused('The archive lists ' . self::plain($key, 200) . ' more than once.');
            }
            $seen[$key] = true;
            $folds[$folded] = true;

            $size = $dir ? 0 : (int) $stat['size'];
            if ($size > $limits['max_file_bytes']) {
                throw new PackageRefused("{$key} is " . self::megabytes($size) . '; one file may be at most ' . self::megabytes($limits['max_file_bytes']) . '.');
            }
            $total += $size;
            if ($total > $limits['max_total_bytes']) {
                throw new PackageRefused('The archive unpacks to more than ' . self::megabytes($limits['max_total_bytes']) . ', the most a package may be.');
            }

            $segments = explode('/', $key);
            if (count($segments) === 1 && ! $dir) {
                throw new PackageRefused("{$key} sits outside the package folder. " . self::shapeHint());
            }
            $tops[$segments[0]] = true;

            $entries[] = ['index' => $i, 'name' => $name, 'dir' => $dir, 'size' => $size];
        }

        if (count($tops) !== 1) {
            throw new PackageRefused(count($tops) === 0 ? 'The archive is empty.' : 'The archive holds more than one folder at the top. ' . self::shapeHint());
        }

        $slug = (string) array_key_first($tops);
        if (strlen($slug) > self::SLUG_MAX || ! preg_match(self::SLUG_PATTERN, $slug)) {
            throw new PackageRefused("The package folder \"{$slug}\" is not a valid name: use lowercase letters, digits and single hyphens, starting with a letter.");
        }

        $hasTheme = isset($seen["{$slug}/theme.json"]);
        $hasExtension = isset($seen["{$slug}/extension.json"]);

        if ($hasTheme && $hasExtension) {
            throw new PackageRefused("{$slug}/ holds both a theme.json and an extension.json. A package is a theme or an extension, not both.");
        }
        if (! $hasTheme && ! $hasExtension) {
            throw new PackageRefused("{$slug}/ has no theme.json or extension.json, so it is not a theme or an extension.");
        }

        $kind = $hasTheme ? 'theme' : 'extension';
        $manifest = self::readManifest($zip, "{$slug}/{$kind}.json");

        self::assertManifest($kind, $slug, $manifest);

        $archive = new self($zip, $slug, $kind, $manifest);
        $archive->entries = $entries;

        return $archive;
    }

    /** The package folder's name: a theme's slug, or an extension's name. */
    public function slug(): string
    {
        return $this->slug;
    }

    /** theme or extension. */
    public function kind(): string
    {
        return $this->kind;
    }

    /** @return array<string, mixed> */
    public function manifest(): array
    {
        return $this->manifest;
    }

    public function version(): string
    {
        return (string) $this->manifest['version'];
    }

    /** The name an admin knows the package by. */
    public function title(): string
    {
        $title = $this->kind === 'theme' ? ($this->manifest['name'] ?? '') : ($this->manifest['title'] ?? '');

        $title = is_string($title) ? self::plain($title, self::TITLE_MAX) : '';

        return $title !== '' ? $title : $this->slug;
    }

    /**
     * $text made safe to show and to log: control characters and line
     * breaks become spaces, so a manifest cannot forge a line of the log,
     * and it is cut to $max characters.
     */
    public static function plain(string $text, int $max = 200): string
    {
        $text = mb_scrub($text, 'UTF-8');
        $text = trim((string) preg_replace('/[\p{Cc}\x{2028}\x{2029}]+/u', ' ', $text));

        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, max(1, $max - 3))) . '...';
    }

    /**
     * The migration files the package brings, as Laravel names them (the
     * file name without .php), each with the folder inside the package it
     * comes from: database/migrations, or extensions/{name}/database/migrations.
     *
     * @return list<array{name: string, folder: string}>
     */
    public function migrationNames(): array
    {
        $found = [];
        $pattern = '#^' . preg_quote($this->slug, '#') . '/((?:extensions/[^/]+/)?database/migrations)/([^/]+)\.php$#';

        foreach ($this->entries as $entry) {
            if (! $entry['dir'] && preg_match($pattern, $entry['name'], $m)) {
                $found[] = ['name' => $m[2], 'folder' => $m[1]];
            }
        }

        return $found;
    }

    /**
     * Manifests of the extensions the package brings in its own extensions/
     * folder, read from the archive, keyed by their folder.
     *
     * @return array<string, array<string, mixed>>
     */
    public function bundledExtensions(): array
    {
        if ($this->bundled !== null) {
            return $this->bundled;
        }

        $found = [];

        foreach ($this->entries as $entry) {
            if (preg_match('#^' . preg_quote($this->slug, '#') . '/extensions/([^/]+)/extension\.json$#', $entry['name'], $m)) {
                $found[$m[1]] = self::readManifest($this->zip, $entry['name']);
            }
        }

        return $this->bundled = $found;
    }

    /**
     * Writes the archive under $directory and returns the package folder.
     * $directory must be empty: files are created, never overwritten.
     *
     * @throws PackageRefused
     */
    public function extractTo(string $directory): string
    {
        $base = realpath($directory);
        if ($base === false || ! is_dir($base)) {
            throw new \RuntimeException('The folder to unpack into does not exist.');
        }

        foreach ($this->entries as $entry) {
            $target = $base . '/' . rtrim($entry['name'], '/');

            if ($entry['dir']) {
                self::makeDirectory($target, $base);

                continue;
            }

            self::makeDirectory(dirname($target), $base);
            $this->writeEntry($entry, $target);
        }

        // Read while the archive is still open.
        $this->bundledExtensions();
        $this->zip->close();

        return $base . '/' . $this->slug;
    }

    /** @param array{index: int, name: string, dir: bool, size: int} $entry */
    private function writeEntry(array $entry, string $target): void
    {
        $in = $this->zip->getStreamIndex($entry['index'], ZipArchive::FL_UNCHANGED);
        if ($in === false) {
            throw new PackageRefused("{$entry['name']} cannot be read from the archive.");
        }

        $out = @fopen($target, 'xb');
        if ($out === false) {
            fclose($in);
            throw new \RuntimeException('A file of the package could not be written.');
        }

        $written = 0;
        try {
            while (! feof($in)) {
                $chunk = fread($in, 65536);
                if ($chunk === false) {
                    throw new PackageRefused("{$entry['name']} cannot be read from the archive.");
                }
                $written += strlen($chunk);
                // The size in the archive's directory is what every limit
                // was checked against; an entry that inflates past it is
                // lying about itself.
                if ($written > $entry['size']) {
                    throw new PackageRefused("{$entry['name']} unpacks to more than the archive says it holds.");
                }
                fwrite($out, $chunk);
            }
        } finally {
            fclose($in);
            fclose($out);
        }

        chmod($target, 0644);
    }

    /** Creates $path (and its parents) and makes sure it is inside $base. */
    private static function makeDirectory(string $path, string $base): void
    {
        if (! is_dir($path) && ! @mkdir($path, 0755, true) && ! is_dir($path)) {
            throw new \RuntimeException('A folder of the package could not be created.');
        }

        $real = realpath($path);
        if ($real === false || ($real !== $base && ! str_starts_with($real, $base . '/'))) {
            throw new PackageRefused('The archive tries to write outside its own folder.');
        }
    }

    private static function skipped(string $name): bool
    {
        foreach (self::SKIPPED as $skip) {
            if (str_ends_with($skip, '/') ? str_starts_with($name, $skip) : basename($name) === $skip) {
                return true;
            }
        }

        return false;
    }

    /** @throws PackageRefused */
    private static function assertSafeName(string $name): void
    {
        $shown = mb_substr(preg_replace('/[^\x20-\x7E]/', '?', $name) ?? '', 0, 200);

        if ($name === '' || str_contains($name, "\0") || preg_match('/[\x00-\x1F\x7F]/', $name)) {
            throw new PackageRefused("The archive holds an entry with an unreadable name ({$shown}).");
        }
        if (str_contains($name, '\\')) {
            throw new PackageRefused("The archive holds a path written with backslashes ({$shown}). Make the zip again with a standard zip tool.");
        }
        if (str_starts_with($name, '/') || preg_match('/^[A-Za-z]:/', $name)) {
            throw new PackageRefused("The archive holds an absolute path ({$shown}). Every path must sit inside the package folder.");
        }
        foreach (explode('/', rtrim($name, '/')) as $segment) {
            if ($segment === '..' || $segment === '.' || $segment === '') {
                throw new PackageRefused("The archive holds a path that leaves its folder or is not plain ({$shown}).");
            }
            // Characters and names other systems treat specially: a colon
            // opens an NTFS stream, Windows drops trailing dots and spaces,
            // and device names never reach a file.
            if (strpbrk($segment, ':*?"<>|') !== false || preg_match('/[. ]$/', $segment) || preg_match(self::DEVICE_NAMES, $segment)) {
                throw new PackageRefused("The archive holds a file name that not every system can store ({$shown}). Rename it and make the zip again.");
            }
        }
        if (strlen($name) > 400) {
            throw new PackageRefused("The archive holds a path longer than 400 characters ({$shown}).");
        }
    }

    /** The entry's Unix file type, or null when the archive was not made on Unix. */
    private static function unixType(ZipArchive $zip, int $index): ?int
    {
        $system = 0;
        $attributes = 0;

        if (! $zip->getExternalAttributesIndex($index, $system, $attributes, ZipArchive::FL_UNCHANGED)) {
            return null;
        }

        if ($system !== ZipArchive::OPSYS_UNIX) {
            return null;
        }

        $type = ($attributes >> 16) & self::TYPE_MASK;

        return $type === 0 ? null : $type;
    }

    /** @return array<string, mixed> */
    private static function readManifest(ZipArchive $zip, string $name): array
    {
        $stat = $zip->statName($name);
        if ($stat === false || $stat['size'] > self::MANIFEST_MAX_BYTES) {
            throw new PackageRefused("{$name} is missing or larger than a manifest can be.");
        }

        $json = $zip->getFromName($name, self::MANIFEST_MAX_BYTES + 1);
        $manifest = is_string($json) ? json_decode($json, true) : null;

        if (! is_array($manifest) || array_is_list($manifest)) {
            throw new PackageRefused("{$name} is not valid JSON.");
        }

        return $manifest;
    }

    /** @throws PackageRefused */
    private static function assertManifest(string $kind, string $slug, array $manifest): void
    {
        $file = "{$slug}/{$kind}.json";

        if ($kind === 'extension') {
            $name = $manifest['name'] ?? null;
            if (! is_string($name) || $name === '') {
                throw new PackageRefused("{$file} needs a name.");
            }
            if ($name !== $slug) {
                throw new PackageRefused("{$file} names the extension \"" . self::plain($name, 80) . "\", but its folder is \"{$slug}\". The two must match.");
            }
            if (isset($manifest['provider']) && ! self::validProvider($manifest['provider'])) {
                throw new PackageRefused("{$file} names a provider class that an extension may not use.");
            }
        } else {
            if (! is_string($manifest['name'] ?? null) || $manifest['name'] === '') {
                throw new PackageRefused("{$file} needs a name.");
            }
            if (isset($manifest['slug']) && $manifest['slug'] !== $slug) {
                $named = is_string($manifest['slug']) ? self::plain($manifest['slug'], 80) : 'something else';
                throw new PackageRefused("{$file} names the theme \"{$named}\", but its folder is \"{$slug}\". The two must match.");
            }
            if (isset($manifest['provider']) && ! self::validProvider($manifest['provider'])) {
                throw new PackageRefused("{$file} names a provider class that a theme may not use.");
            }
        }

        $version = $manifest['version'] ?? null;
        if (! is_string($version) || strlen($version) > self::VERSION_MAX || ! preg_match(self::VERSION_PATTERN, $version)) {
            throw new PackageRefused("{$file} needs a version such as 1.0.0, of at most " . self::VERSION_MAX . ' characters.');
        }
    }

    /**
     * A provider is a class in the package's own namespace. The kernel maps
     * that namespace to the package's src/ folder, so a package claiming one
     * of the application's or the framework's namespaces could stand in for
     * their classes.
     */
    public static function validProvider(mixed $provider): bool
    {
        if (! is_string($provider) || ! preg_match('/^[A-Z][A-Za-z0-9_]*(?:\\\\[A-Z][A-Za-z0-9_]*)+$/', $provider)) {
            return false;
        }

        // PHP namespaces ignore case, so APP\Support is App\Support.
        foreach (self::RESERVED_NAMESPACES as $reserved) {
            if (self::namespacesOverlap(self::providerNamespace($provider), $reserved)) {
                return false;
            }
        }

        return true;
    }

    /** The namespace a provider class is in, with its trailing backslash. */
    public static function providerNamespace(string $provider): string
    {
        $cut = strrpos($provider, '\\');

        return $cut === false ? '' : substr($provider, 0, $cut + 1);
    }

    /**
     * Whether two namespaces (each ending in a backslash) share classes:
     * one is the other, or holds it. Compared without regard to case, as
     * PHP compares class names.
     */
    public static function namespacesOverlap(string $a, string $b): bool
    {
        $a = strtolower(rtrim($a, '\\') . '\\');
        $b = strtolower(rtrim($b, '\\') . '\\');

        return str_starts_with($a, $b) || str_starts_with($b, $a);
    }

    private static function shapeHint(): string
    {
        return 'A package zip holds one folder named after the package (for example sample/), with its theme.json or extension.json inside.';
    }

    public static function megabytes(int $bytes): string
    {
        if ($bytes < 1048576) {
            return max(1, (int) round($bytes / 1024)) . ' KB';
        }

        return rtrim(rtrim(number_format($bytes / 1048576, 1), '0'), '.') . ' MB';
    }
}
