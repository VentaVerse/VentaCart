<?php

namespace Themes\Xelga\Extensions;

use App\Services\ExtensionManager;
use Illuminate\Support\Facades\Log;

/**
 * What a listener of the `xelga.extend` event receives: the only methods an
 * extension calls. Xelga fires the event the first time in a request that
 * it reads its catalog, which is after every provider has booted, so the
 * order extensions boot in does not matter, and an extension needs no
 * Xelga class to register.
 *
 * Every check that fails is logged and skips that part; nothing throws, so
 * a broken extension never takes the storefront down.
 */
final class Registrar
{
    public const EVENT = 'xelga.extend';

    /** The version of the hooks this Xelga offers; a parts folder names the one it was written for. */
    public const API = 1;

    public const NAME = '/^[a-z][a-z0-9-]{0,39}$/';

    /** @var array<string, ExtensionParts> */
    private array $parts = [];

    /** @var array<string, \Closure> */
    private array $previews = [];

    /** @var array<string, true> problems already logged in this process */
    private static array $logged = [];

    public function __construct(private readonly ExtensionManager $extensions)
    {
    }

    /**
     * Register the extension's parts folder.
     *
     * @param  string  $extension  the extension's name, as in its extension.json
     * @param  string  $path  its parts folder, inside the extension's own folder
     */
    public function parts(string $extension, string $path): void
    {
        $refuse = fn (string $reason, array $context = []) => self::refuse($extension, $reason, $context);

        if (preg_match(self::NAME, $extension) !== 1) {
            $refuse('the name is 1 to 40 lower case letters, digits or hyphens, starting with a letter');

            return;
        }
        if (isset($this->parts[$extension])) {
            $refuse('it is registered already');

            return;
        }
        if ($this->extensions->state($extension) !== 'enabled') {
            $refuse('the extension is not installed and switched on');

            return;
        }

        $manifest = (array) $this->extensions->discovered()->get($extension, []);
        $root = realpath((string) ($manifest['path'] ?? ''));
        $real = realpath($path);
        if ($root === false || $real === false || ! is_dir($real) || ! str_starts_with($real . '/', $root . '/') || $real === $root) {
            $refuse('the parts folder must be a folder inside the extension\'s own folder', ['path' => $path]);

            return;
        }
        if (self::holdsLinks($real)) {
            $refuse('the parts folder holds a link; parts must be plain files');

            return;
        }

        $meta = json_decode((string) @file_get_contents($real . '/xelga.json'), true);
        if (! is_array($meta) || ($meta['api'] ?? null) !== self::API) {
            $refuse('xelga.json is missing, or names a hooks API this Xelga does not offer', ['offered' => self::API]);

            return;
        }

        $stylesheet = null;
        if (isset($meta['stylesheet'])) {
            $stylesheet = self::stylesheet($root, (string) $manifest['path'], $meta['stylesheet']);
            if ($stylesheet === null) {
                $refuse('the stylesheet must be a .css file under the extension\'s resources folder');

                return;
            }
        }

        $provider = (string) ($manifest['provider'] ?? '');
        $namespace = str_contains($provider, '\\') ? substr($provider, 0, (int) strrpos($provider, '\\') + 1) : '';

        $this->parts[$extension] = new ExtensionParts(
            name: $extension,
            title: trim((string) ($manifest['title'] ?? '')) !== '' ? (string) $manifest['title'] : $extension,
            version: (string) ($manifest['version'] ?? '0'),
            path: $real,
            namespace: $namespace,
            stylesheet: $stylesheet,
        );
    }

    /**
     * Where the editor previews an extension template: a page of this store
     * to open, or null while there is nothing to show yet.
     *
     * @param  \Closure(): ?string  $resolve
     */
    public function previewUrl(string $pageType, \Closure $resolve): void
    {
        if (preg_match(ExtensionParts::PAGE_TYPE, $pageType) !== 1) {
            self::refuse($pageType, 'a preview address needs a page type');

            return;
        }

        $this->previews[$pageType] = $resolve;
    }

    /** @return array<string, ExtensionParts> */
    public function registered(): array
    {
        ksort($this->parts);

        return $this->parts;
    }

    /** @return array<string, \Closure> */
    public function previews(): array
    {
        return $this->previews;
    }

    public static function refuse(string $extension, string $reason, array $context = []): void
    {
        $key = $extension . '|' . $reason;
        if (isset(self::$logged[$key])) {
            return;
        }
        self::$logged[$key] = true;
        Log::warning("Xelga skipped part of the extension {$extension}: {$reason}.", ['extension' => $extension] + $context);
    }

    /** Forget which problems were logged (tests). */
    public static function forgetLogged(): void
    {
        self::$logged = [];
    }

    private static function holdsLinks(string $dir): bool
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );
        $count = 0;
        foreach ($items as $item) {
            if ($item->isLink() || ++$count > 2000) {
                return true;
            }
        }

        return false;
    }

    /** The stylesheet as a build entry relative to the app, or null when it is not one the extension may name. */
    private static function stylesheet(string $root, string $folder, mixed $relative): ?string
    {
        if (! is_string($relative) || ! str_ends_with($relative, '.css') || str_contains($relative, '..') || str_contains($relative, '\\')) {
            return null;
        }
        $relative = ltrim($relative, '/');
        $real = realpath($root . '/' . $relative);
        if ($real === false || ! is_file($real) || ! str_starts_with($real, $root . '/resources/')) {
            return null;
        }
        // The build entry is the folder as the app sees it: an extension may
        // be a link to a package being developed elsewhere.
        $base = rtrim(base_path(), '/') . '/';
        $folder = rtrim($folder, '/');

        return str_starts_with($folder . '/', $base) ? substr($folder, strlen($base)) . '/' . $relative : null;
    }
}
