<?php

namespace Themes\Xelga\Extensions;

use App\Services\ExtensionManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;

/**
 * The extension parts registered for this request, collected once by
 * firing `xelga.extend` (scoped binding; Storefront::forgetRequestState
 * drops it). A store without the extension has no listener; a store
 * without Xelga never fires the event.
 */
final class Extensions
{
    /** @var array<string, ExtensionParts>|null */
    private ?array $parts = null;

    /** @var array<string, \Closure> */
    private array $previews = [];

    public function __construct(private readonly ExtensionManager $extensions)
    {
    }

    /** The instance for this request. */
    public static function current(): self
    {
        return app(self::class);
    }

    /** @return array<string, ExtensionParts> by extension name, sorted */
    public function all(): array
    {
        if ($this->parts !== null) {
            return $this->parts;
        }

        $registrar = new Registrar($this->extensions);
        try {
            Event::dispatch(Registrar::EVENT, [$registrar]);
        } catch (\Throwable $e) {
            // One listener failing keeps what the others registered before it.
            report($e);
        }

        $parts = $registrar->registered();
        foreach ($parts as $part) {
            View::replaceNamespace($part->viewNamespace(), $part->path . '/views');
        }

        // Before every provider has booted, an extension may not have
        // listened yet: the answer is not kept, so the next read asks again.
        if (! app()->isBooted()) {
            return $parts;
        }
        $this->previews = $registrar->previews();

        return $this->parts = $parts;
    }

    /**
     * The stylesheets of the registered extensions, after the theme's own,
     * so a section added live in the editor is already styled. The store's
     * build (or the build a package ships, see App\Support\PackageAssets)
     * comes first; an entry no build has is served as the file ships, from
     * its own versioned address (route xelga.extension-css), so an
     * extension installed from an upload is styled without a rebuild.
     */
    public function stylesheetTags(): \Illuminate\Support\HtmlString
    {
        $html = '';
        foreach ($this->all() as $part) {
            if ($part->stylesheet === null) {
                continue;
            }
            if (self::built($part->stylesheet)) {
                $html .= app(\Illuminate\Foundation\Vite::class)($part->stylesheet)->toHtml();
                continue;
            }
            $hash = $part->stylesheetHash();
            if ($hash !== null) {
                $html .= '<link rel="stylesheet" href="' . e(route('xelga.extension-css', ['extension' => $part->name, 'hash' => $hash], false)) . '">';
            }
        }

        return new \Illuminate\Support\HtmlString($html);
    }

    /** Whether the store's build, or a build a package ships, has this entry. */
    public static function built(string $entry): bool
    {
        try {
            app(\Illuminate\Foundation\Vite::class)($entry)->toHtml();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Every installed extension (switched on or off), for the editor's
     * notes about sections whose extension is away: its title and whether
     * it is on or off. One that is not installed is not listed; the editor
     * names it by the type's prefix as not installed.
     *
     * @return array<string, array{title: string, state: string}>
     */
    public function summary(): array
    {
        $out = [];
        foreach ($this->extensions->discovered() as $name => $manifest) {
            if (($manifest['type'] ?? null) === 'theme' || preg_match(Registrar::NAME, (string) $name) !== 1
                || $this->extensions->state((string) $name) === 'not_installed') {
                continue;
            }
            $out[$name] = [
                'title' => trim((string) ($manifest['title'] ?? '')) !== '' ? (string) $manifest['title'] : (string) $name,
                'state' => isset($this->all()[$name]) ? 'on' : $this->absence((string) $name),
            ];
        }

        return $out;
    }

    public function get(string $name): ?ExtensionParts
    {
        return $this->all()[$name] ?? null;
    }

    /** 'marketplace@1.2.0,store-credit@1.0.0': part of the catalog's cache key. */
    public function fingerprint(): string
    {
        return implode(',', array_map(fn (ExtensionParts $p) => $p->name . '@' . $p->version, $this->all()));
    }

    /** The registered preview address of an extension template, or null. */
    public function previewUrl(string $pageType): ?string
    {
        $this->all();
        $resolve = $this->previews[$pageType] ?? null;
        if ($resolve === null) {
            return null;
        }

        try {
            $url = $resolve();
        } catch (\Throwable $e) {
            report($e);

            return null;
        }

        // Only a page of this store opens in the preview.
        if (! is_string($url) || $url === '') {
            return null;
        }
        $origin = rtrim(url('/'), '/');

        return $url === $origin || str_starts_with($url, $origin . '/') ? $url : null;
    }

    /**
     * The name of an extension for the editor, also for one that is not
     * registered now (the owner of a dormant section): its title from its
     * extension.json when it is on disk, else its name.
     */
    public function titleOf(string $name): string
    {
        $part = $this->get($name);
        if ($part !== null) {
            return $part->title;
        }
        $manifest = $this->extensions->discovered()->get($name);
        $title = is_array($manifest) ? trim((string) ($manifest['title'] ?? '')) : '';

        return $title !== '' ? $title : $name;
    }

    /** Whether an extension that is not registered now is switched off ('off') or not installed at all ('missing'). */
    public function absence(string $name): string
    {
        $state = $this->extensions->state($name);

        return in_array($state, ['disabled', 'needs_migration', 'enabled'], true) ? 'off' : 'missing';
    }
}
