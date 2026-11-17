<?php

namespace Themes\Xelga\Catalog;

use Themes\Xelga\Documents\Defaults;
use Themes\Xelga\Extensions\ExtensionParts;
use Themes\Xelga\Documents\Handles;
use Themes\Xelga\Documents\Shape;
use Themes\Xelga\Documents\ShapeException;
use Themes\Xelga\Settings\SanitizeContext;
use Themes\Xelga\Settings\ContrastCheck;
use Themes\Xelga\Settings\Sanitizer;
use Themes\Xelga\StoreTemplates\Portable;
use Themes\Xelga\StoreTemplates\StoreTemplates;

/**
 * Checks every schema file, the theme settings, the font pairs and the
 * shipped default documents. Used by the theme's tests and by
 * `php artisan xelga:lint`. Returns the problems as sentences.
 */
final class CatalogLinter
{
    private const GROUPS = ['header', 'footer', 'template'];

    /** @var list<int> the widths, in twelfths of a row, a section may ask for */
    private const SPANS = [3, 4, 6, 8, 12];

    private const LIVE = ['text', 'attr', 'css', 'render'];

    /** `render`: the setting is read by the views, so a change shows when the page is drawn again. */
    private const TARGETS = ['token', 'attr', 'font-pair', 'scheme-colors', 'render'];

    /** @var list<string> */
    private array $problems = [];

    /** @return list<string> */
    public static function check(): array
    {
        Catalog::flush();

        return (new self())->run();
    }

    /**
     * Checks one registered extension's parts folder with the theme's own
     * rules, plus the rules for extension parts.
     *
     * @return list<string>
     */
    public static function checkExtension(string $name): array
    {
        Catalog::flush();
        $part = Catalog::extensions()->get($name);
        if ($part === null) {
            return ["{$name} has no Xelga parts registered: it is not installed and switched on, or its parts were refused (see the log)."];
        }

        return (new self())->runExtension($part);
    }

    /** @return list<string> */
    private function runExtension(ExtensionParts $part): array
    {
        $css = $part->stylesheet !== null ? (string) @file_get_contents(base_path($part->stylesheet)) : '';
        foreach ($part->schemaFiles() as $file) {
            $name = basename($file, '.json');
            $where = "{$part->name}: section {$name}";
            $spec = Catalog::extensionSection($part, $file, fn (string $reason) => $this->problem("{$where}: {$reason}."));
            if ($spec === null) {
                continue;
            }
            $this->section($name, $spec, $part);
            $this->extensionRanges($where, $spec, $css);
        }

        $this->extensionTemplates($part);
        $this->extensionPlacements($part);

        foreach ($part->blockFiles() as $file) {
            $name = basename($file, '.json');
            $where = "{$part->name}: block {$name}";
            $spec = Catalog::extensionBlock($part, $file, fn (string $reason) => $this->problem("{$where}: {$reason}."));
            if ($spec === null) {
                continue;
            }
            $this->settings($where, $spec['settings'], $spec);
            foreach ($spec['targets'] as $target) {
                $section = Catalog::section($target);
                if ($section === null || $section->source() !== null) {
                    $this->problem("{$where}: targets {$target}, which is not one of the theme's sections.");
                } elseif (($section->raw['extension_blocks'] ?? false) !== true) {
                    $this->problem("{$where}: targets {$target}, which does not take extension blocks.");
                }
            }
            $view = $part->path . "/views/blocks/{$name}.blade.php";
            if (! is_file($view) || is_link($view)) {
                $this->problem("{$where}: views/blocks/{$name}.blade.php is missing.");
            }
            // A block's view puts the attribute on its own element, inside the theme's wrapper.
            $this->extensionRanges($where, ['type' => $name, 'settings' => $spec['settings']], $css, 'b-', ' ');
        }

        return $this->problems;
    }

    /**
     * An extension's template defaults: a page type core knows, the same
     * checks as a merchant's document, exactly one main section and it the
     * extension's own, and no record a picker or a link names, since
     * defaults belong to no store.
     */
    private function extensionTemplates(ExtensionParts $part): void
    {
        $handles = Handles::extensionTemplates();
        foreach ($part->templates() as $type => $file) {
            $where = "{$part->name}: templates/{$type}.json";
            if (($handles[$type] ?? null) !== $part->name) {
                $this->problem("{$where}: {$type} is not a page type the extension registered with core (StorefrontPageType::register), or another extension ships it.");
                continue;
            }
            $content = json_decode((string) file_get_contents($file), true);
            if (! is_array($content)) {
                $this->problem("{$where}: not valid JSON.");
                continue;
            }
            $mains = [];
            foreach ((array) ($content['sections'] ?? []) as $id => $section) {
                $schema = is_array($section) ? Catalog::section((string) ($section['type'] ?? '')) : null;
                if ($schema === null) {
                    continue;
                }
                if (($schema->raw['main'] ?? false) === true) {
                    $mains[] = $schema;
                }
                $settings = [[$schema->settings(), (array) ($section['settings'] ?? [])]];
                foreach ((array) ($section['blocks'] ?? []) as $block) {
                    $settings[] = [$schema->blockSettings((string) ($block['type'] ?? '')), (array) ($block['settings'] ?? [])];
                }
                foreach ($settings as [$schemaSettings, $values]) {
                    foreach ($values as $key => $value) {
                        $setting = $schemaSettings[$key] ?? null;
                        $isRecord = $setting !== null && (isset(SanitizeContext::PICKER_RECORDS[$setting->type]) || ($setting->type === 'url' && is_array($value) && isset($value['id'])));
                        if ($isRecord && $value !== null && $value !== [] && $value !== '') {
                            $this->problem("{$where}: {$id} names a store's record in {$key}; defaults belong to no store.");
                        }
                    }
                }
            }
            if (count($mains) !== 1 || $mains[0]->source() !== $part->name) {
                $this->problem("{$where}: holds exactly one main section, and it is one of the extension's own.");
            }
            $this->lintDocument("{$part->name}: templates", $type, $content, ['scheme-1', 'scheme-2', 'scheme-3']);
        }
    }

    /** Placements: each well formed, placing the extension's own section or block where it is allowed. */
    private function extensionPlacements(ExtensionParts $part): void
    {
        $where = "{$part->name}: placements.json";
        $pageTypes = [...Handles::TEMPLATES, ...array_keys(Handles::extensionTemplates())];
        $compiled = \Themes\Xelga\Extensions\Placements::compile($part, Catalog::sections(), fn (string $reason) => $this->problem("{$where}: {$reason}."));
        foreach ($compiled as $placement) {
            if ($placement['kind'] !== 'section') {
                continue;
            }
            $schema = Catalog::section($placement['section']);
            foreach ($placement['templates'] as $type) {
                if (! in_array($type, $pageTypes, true)) {
                    $this->problem("{$where}: placement {$placement['id']} names {$type}, which is not a page template.");
                } elseif (! $schema->allowedIn($type, 'template', $type)) {
                    $this->problem("{$where}: placement {$placement['id']} puts {$placement['section']} on {$type}, where it is not allowed.");
                }
            }
        }
    }

    /** The extension's stylesheet must hold a rule for every range value of its sections. */
    private function extensionRanges(string $where, array $spec, string $css, string $prefix = 's-', string $join = ''): void
    {
        $class = $prefix . str_replace('.', '--', $spec['type']);
        $rules = [];
        foreach ((array) ($spec['settings'] ?? []) as $raw) {
            if (($raw['type'] ?? null) === 'range') {
                $rules[] = [new SettingSchema($raw), ".{$class}{$join}"];
            }
        }
        foreach ((array) ($spec['blocks']['types'] ?? []) as $type) {
            foreach ((array) ($type['settings'] ?? []) as $raw) {
                if (($raw['type'] ?? null) === 'range') {
                    $rules[] = [new SettingSchema($raw), ".{$class} [data-block-type=\"{$type['type']}\"]"];
                }
            }
        }
        foreach ($rules as [$setting, $selector]) {
            $attr = str_replace('_', '-', $setting->key);
            foreach ($setting->rangeValues() as $value) {
                if (! str_contains($css, "{$selector}[data-{$attr}=\"{$value}\"]{--{$attr}:{$value}}")) {
                    $this->problem("{$where}: the stylesheet has no rule for {$setting->key} = {$value}; run node resources/themes/xelga/bin/ranges.mjs --schemas <its schemas folder> --out <its stylesheet>.");
                    break 2;
                }
            }
        }
    }

    /** @return list<string> */
    private function run(): array
    {
        $types = [];
        foreach (Catalog::schemaFiles() as $file) {
            $spec = json_decode((string) file_get_contents($file), true);
            $name = basename($file, '.json');
            if (! is_array($spec)) {
                $this->problem("schemas/sections/{$name}.json is not valid JSON.");
                continue;
            }
            $types[] = $name;
            $this->section($name, $spec);
        }

        $this->containers();
        $this->themeSettings();
        $this->fontPairs();
        $this->defaults();
        $this->storeTemplates();
        $this->ranges();
        $this->swatches();

        return $this->problems;
    }

    private function section(string $name, array $spec, ?ExtensionParts $part = null): void
    {
        $where = $part === null ? "Section {$name}" : "{$part->name}: section {$name}";
        if ($part === null && ($spec['type'] ?? null) !== $name) {
            $this->problem("{$where}: type must equal the file name.");
        }
        foreach (['label', 'class', 'group'] as $key) {
            if (! is_string($spec[$key] ?? null) || $spec[$key] === '') {
                $this->problem("{$where}: needs a {$key}.");
            }
        }
        if (! in_array($spec['group'] ?? null, self::GROUPS, true)) {
            $this->problem("{$where}: group must be header, footer or template.");
        }
        if (($spec['group'] ?? null) === 'template') {
            foreach ((array) ($spec['templates'] ?? []) as $template) {
                if (! in_array($template, [...Handles::TEMPLATES, ...self::extensionPageTypes(), '*', '*custom', '*extension'], true)) {
                    $this->problem("{$where}: unknown template {$template}.");
                }
            }
            if (($spec['templates'] ?? []) === []) {
                $this->problem("{$where}: lists no templates it may be added to.");
            }
        }

        $this->spans($where, $spec, $part === null);

        $settings = (array) ($spec['settings'] ?? []);
        $this->settings($where, $settings, $spec);

        $renderKeys = (array) ($spec['render_keys'] ?? []);
        foreach ($renderKeys as $key) {
            if (! in_array($key, array_column($settings, 'key'), true)) {
                $this->problem("{$where}: render key {$key} is not a setting.");
            }
        }

        $variants = array_column((array) ($spec['variants'] ?? []), 'value');
        $variantSetting = collect($settings)->firstWhere('key', 'variant');
        if ($variants !== [] && $variantSetting !== null) {
            $options = array_column((array) ($variantSetting['options'] ?? []), 'value');
            if ($options !== $variants) {
                $this->problem("{$where}: the variant setting's options must match its variants.");
            }
        }

        $blocks = (array) ($spec['blocks'] ?? []);
        if ((int) ($blocks['min'] ?? 0) > (int) ($blocks['max'] ?? 0)) {
            $this->problem("{$where}: blocks min is above max.");
        }
        $blockTypes = [];
        foreach ((array) ($blocks['types'] ?? []) as $type) {
            $bt = (string) ($type['type'] ?? '');
            if ($bt === '' || in_array($bt, $blockTypes, true)) {
                $this->problem("{$where}: a block type is missing or used twice ({$bt}).");
            }
            $blockTypes[] = $bt;
            if (! is_string($type['label'] ?? null)) {
                $this->problem("{$where}: block {$bt} needs a label.");
            }
            $this->settings("{$where} block {$bt}", (array) ($type['settings'] ?? []), $spec);
        }

        foreach ((array) ($spec['presets'] ?? []) as $preset) {
            foreach (array_keys((array) ($preset['settings'] ?? [])) as $key) {
                if (! in_array($key, array_column($settings, 'key'), true)) {
                    $this->problem("{$where}: preset {$preset['key']} sets unknown setting {$key}.");
                }
            }
            foreach ((array) ($preset['blocks'] ?? []) as $block) {
                if (! in_array($block['type'] ?? null, $blockTypes, true)) {
                    $this->problem("{$where}: preset {$preset['key']} adds unknown block type.");
                }
            }
        }

        if ($part !== null) {
            if (! is_file($part->path . "/views/sections/{$name}.blade.php")) {
                $this->problem("{$where}: views/sections/{$name}.blade.php is missing.");
            }
            if (is_link($part->path . "/views/sections/{$name}.blade.php")) {
                $this->problem("{$where}: views/sections/{$name}.blade.php is a link.");
            }

            return;
        }
        if (! is_file(Catalog::root("views/sections/{$name}.blade.php"))) {
            $this->problem("{$where}: views/sections/{$name}.blade.php is missing.");
        }
        if (! is_file(Catalog::root("css/sections/{$name}.css"))) {
            $this->problem("{$where}: css/sections/{$name}.css is missing.");
        }
    }

    /**
     * A section's width in columns. Every section, the theme's or an
     * extension's, may only name the allowed widths, and a main section
     * (the one that is a page's content) is never narrow. Only the theme's
     * own sections must also set up their container: how an extension's
     * section narrows is its stylesheet's business, and that file may be
     * generated or built elsewhere.
     */
    private function spans(string $where, array $spec, bool $own): void
    {
        $allowed = 'must be 3, 4, 6, 8 or 12';
        $values = [];
        if (array_key_exists('span', $spec)) {
            $min = is_array($spec['span']) ? ($spec['span']['min'] ?? null) : null;
            if (! in_array($min, self::SPANS, true)) {
                $this->problem("{$where}: span.min {$allowed}.");
            } else {
                $values[] = $min;
            }
        }
        foreach ((array) ($spec['variants'] ?? []) as $variant) {
            if (! is_array($variant) || ! array_key_exists('span', $variant)) {
                continue;
            }
            if (! in_array($variant['span'], self::SPANS, true)) {
                $this->problem("{$where}: variant " . json_encode($variant['value'] ?? null) . " span {$allowed}.");
            } else {
                $values[] = $variant['span'];
            }
        }
        $narrow = array_filter($values, fn ($v) => $v < 12) !== [];
        if (($spec['main'] ?? false) === true && $narrow) {
            $this->problem("{$where}: a main section is always full width, so no span may be below 12.");
        }

        if ($own && $narrow) {
            $class = (string) ($spec['class'] ?? '');
            $file = Catalog::root('css/sections/' . ($spec['type'] ?? '') . '.css');
            $css = is_file($file) ? $this->stripComments((string) file_get_contents($file)) : '';
            if ($class === '' || preg_match('/(?:^|[{},])\s*\.' . preg_quote($class, '/') . '\s*\{[^{}]*container-type\s*:\s*inline-size/', $css) !== 1) {
                $this->problem("{$where}: can sit in a narrow column, so css/sections/{$spec['type']}.css must set container-type: inline-size on .{$class} itself.");
            }
        }
    }

    /**
     * Where a container may not be set. A column, a side, the content
     * wrapper, `main` and a main section take their width from the page,
     * and a container there would make the sections inside answer to the
     * wrong width; a section sets it on itself.
     */
    private function containers(): void
    {
        $names = ['\.xr__col', '\.xp__side', '\.xp__content'];
        foreach (Catalog::sections() as $spec) {
            if (($spec['main'] ?? false) === true && is_string($spec['class'] ?? null) && ($spec['source'] ?? null) === null) {
                $names[] = '\.' . preg_quote($spec['class'], '/');
            }
        }
        $forbidden = '/(?<![\w.#-])main(?![\w-])|(?:' . implode('|', $names) . ')(?![\w-])/';

        $files = [...(glob(Catalog::root('css/*.css')) ?: []), ...(glob(Catalog::root('css/*/*.css')) ?: [])];
        foreach ($files as $file) {
            $css = $this->stripComments((string) file_get_contents($file));
            preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER);
            foreach ($rules as [, $selector, $body]) {
                if (preg_match('/(?:^|[;\s])container(?:-type)?\s*:/', $body) === 1 && preg_match($forbidden, $selector) === 1) {
                    $this->problem('css/' . substr($file, strlen(Catalog::root('css/'))) . ': ' . trim((string) preg_replace('/\s+/', ' ', $selector)) . ' sets a container; only a section sets one, on itself.');
                }
            }
        }
    }

    private function stripComments(string $css): string
    {
        return (string) preg_replace('#/\*.*?\*/#s', '', $css);
    }

    /** @return list<string> page types extensions registered with core */
    private static function extensionPageTypes(): array
    {
        return array_values(array_filter(array_keys(\App\Support\StorefrontPageType::all()), fn (string $type) => \App\Support\StorefrontPage::isExtensionType($type)));
    }

    private function settings(string $where, array $settings, array $section): void
    {
        $keys = [];
        $sanitizer = new Sanitizer(SanitizeContext::forLint(['scheme-1', 'scheme-2', 'scheme-3', 'scheme-4', 'scheme-5']));

        foreach ($settings as $raw) {
            $key = (string) ($raw['key'] ?? '');
            $type = (string) ($raw['type'] ?? '');
            if ($key === '' || preg_match('/^[a-z][a-z0-9_]{0,40}$/', $key) !== 1) {
                $this->problem("{$where}: a setting key is missing or not snake_case ({$key}).");
                continue;
            }
            if (in_array($key, $keys, true)) {
                $this->problem("{$where}: setting {$key} is listed twice.");
            }
            $keys[] = $key;
            if (! in_array($type, Catalog::TYPES, true)) {
                $this->problem("{$where}: setting {$key} has an unknown type {$type}.");
                continue;
            }
            if (! is_string($raw['label'] ?? null) || $raw['label'] === '') {
                $this->problem("{$where}: setting {$key} needs a label.");
            }
            if (isset($raw['live']) && ! in_array($raw['live'], self::LIVE, true)) {
                $this->problem("{$where}: setting {$key} has an unknown live mode.");
            }

            $setting = new SettingSchema($raw);
            if ($type === 'select') {
                $values = $setting->optionValues();
                if ($setting->optionsSource() === null && $values === []) {
                    $this->problem("{$where}: select {$key} has no options.");
                }
                if (count($values) !== count(array_unique($values))) {
                    $this->problem("{$where}: select {$key} repeats an option.");
                }
            }
            if ($type === 'range') {
                foreach (['min', 'max', 'step'] as $bound) {
                    if (! is_int($raw[$bound] ?? null) && ! is_float($raw[$bound] ?? null)) {
                        $this->problem("{$where}: range {$key} needs a numeric {$bound}.");
                        continue 2;
                    }
                }
                $steps = ($setting->max() - $setting->min()) / $setting->step();
                if ($setting->step() <= 0 || $setting->max() <= $setting->min() || abs($steps - round($steps)) > 1e-9 || $steps > 200) {
                    $this->problem("{$where}: range {$key} is not a finite set of steps from min to max.");
                }
            }

            $before = count($sanitizer->adjusted());
            $sanitizer->clean($setting, $raw['default'] ?? null, $key);
            if (count($sanitizer->adjusted()) !== $before) {
                $this->problem("{$where}: the default of {$key} does not pass its own rules.");
            }
        }
    }

    private function themeSettings(): void
    {
        $keys = [];
        $schemeSettings = 0;
        foreach (Catalog::themeSettingGroups() as $group) {
            $where = 'Theme settings ' . ($group['group'] ?? '?');
            if (! is_string($group['label'] ?? null)) {
                $this->problem("{$where}: needs a label.");
            }
            if (isset($group['edited_in'])) {
                $this->editedIn($where, $group);
            }
            $this->settings($where, array_values(array_filter((array) $group['settings'], fn ($s) => ($s['type'] ?? null) !== 'scheme-colors')), []);
            foreach ((array) $group['settings'] as $setting) {
                $key = (string) ($setting['key'] ?? '');
                if (in_array($key, $keys, true)) {
                    $this->problem("{$where}: {$key} is used by two settings.");
                }
                $keys[] = $key;
                $target = (array) ($setting['target'] ?? []);
                if (! in_array($target['type'] ?? null, self::TARGETS, true)) {
                    $this->problem("{$where}: {$key} needs a target of token, attr, font-pair, scheme-colors or render.");
                }
                if (($target['type'] ?? null) === 'token' && preg_match('/^--[a-z0-9-]+$/', (string) ($target['name'] ?? '')) !== 1) {
                    $this->problem("{$where}: {$key} names no custom property.");
                }
                if (isset($target['map']) && ($setting['type'] ?? null) === 'select') {
                    foreach ((new SettingSchema($setting))->optionValues() as $value) {
                        if (! isset($target['map'][$value])) {
                            $this->problem("{$where}: {$key} has no token value for {$value}.");
                        }
                    }
                }
                if (isset($target['map']) && ($setting['type'] ?? null) === 'range') {
                    foreach ((new SettingSchema($setting))->rangeValues() as $value) {
                        if (! isset($target['map'][(string) $value])) {
                            $this->problem("{$where}: {$key} has no token value for {$value}.");
                        }
                    }
                }
                if (($setting['type'] ?? null) === 'scheme-colors') {
                    $schemeSettings++;
                    if (count((array) ($setting['roles'] ?? [])) === 0) {
                        $this->problem("{$where}: {$key} lists no roles.");
                    }
                }
            }
        }
        if ($schemeSettings !== 1) {
            $this->problem('Theme settings need exactly one scheme-colors setting.');
        }
    }

    /**
     * A group edited in a section's panel (`edited_in`) is still stored with
     * the theme settings. The panel shows both lists in one form, so the
     * section must exist, the field it follows must be one of the section's
     * own, and no key may be used by both.
     */
    private function editedIn(string $where, array $group): void
    {
        $place = $group['edited_in'];
        $section = is_array($place) ? Catalog::section((string) ($place['section'] ?? '')) : null;
        if ($section === null) {
            $this->problem("{$where}: edited_in names no section.");

            return;
        }
        $own = $section->settings();
        if (isset($place['after']) && ! isset($own[(string) $place['after']])) {
            $this->problem("{$where}: edited_in follows {$place['after']}, which the section does not have.");
        }
        foreach ((array) $group['settings'] as $setting) {
            $key = (string) ($setting['key'] ?? '');
            if (isset($own[$key])) {
                $this->problem("{$where}: {$key} is also a setting of the section it is edited in.");
            }
            if (($setting['type'] ?? null) === 'scheme-colors') {
                $this->problem("{$where}: the scheme colors cannot be edited in a section.");
            }
        }
    }

    private function fontPairs(): void
    {
        $values = [];
        foreach (Catalog::fontPairs() as $pair) {
            $value = (string) ($pair['value'] ?? '');
            $where = "Font pair {$value}";
            if ($value === '' || in_array($value, $values, true)) {
                $this->problem("{$where}: value is missing or used twice.");
            }
            $values[] = $value;
            foreach (['label', 'heading', 'body'] as $key) {
                if (! is_string($pair[$key] ?? null) || $pair[$key] === '') {
                    $this->problem("{$where}: needs {$key}.");
                }
            }
            foreach ((array) ($pair['files'] ?? []) as $file) {
                foreach (['file', 'license'] as $key) {
                    $path = 'assets/fonts/' . basename((string) ($file[$key] ?? ''));
                    if (! is_file(Catalog::root($path))) {
                        $this->problem("{$where}: {$path} is missing.");
                    }
                }
            }
        }
        if ($values === []) {
            $this->problem('There are no font pairs.');
        }
    }

    private function defaults(): void
    {
        $context = SanitizeContext::forLint(array_column((array) (Defaults::shipped(Handles::SETTINGS)['schemes'] ?? []), 'id'));
        foreach (Defaults::handles() as $handle) {
            if (! Handles::known($handle)) {
                $this->problem("store-templates/_defaults/{$handle}.json is not a document the store has.");
                continue;
            }
            try {
                $result = (new Shape($context))->normalize($handle, Defaults::shipped($handle));
                foreach ($result['adjusted'] as $adjusted) {
                    $this->problem("store-templates/_defaults/{$handle}.json: {$adjusted['path']}: {$adjusted['reason']}");
                }
            } catch (ShapeException $e) {
                foreach ($e->errors as $path => $messages) {
                    $this->problem("store-templates/_defaults/{$handle}.json: {$path}: " . implode(' ', $messages));
                }
            }
        }
    }

    /**
     * Every starting store: its documents pass the same checks as a
     * merchant's (nothing is corrected), its look keeps to the look groups and
     * stays readable, and every photo it names ships with it and is used.
     * A store brings no products: the categories and products it names by
     * slug are the merchant's, and a slug the catalog lacks leaves that
     * setting empty. The section presets and the shipped defaults name
     * photos of a store in the media library; those must ship too.
     */
    private function storeTemplates(): void
    {
        StoreTemplates::flush();
        $dirs = array_filter(glob(Catalog::root('store-templates/*'), GLOB_ONLYDIR) ?: [], fn ($d) => basename($d) !== '_defaults');
        if (! in_array(StoreTemplates::DEFAULT, array_map('basename', $dirs), true)) {
            $this->problem('The default starting store ' . StoreTemplates::DEFAULT . ' is missing.');
        }
        foreach ($dirs as $dir) {
            $slug = basename($dir);
            $where = "store-templates/{$slug}";
            $store = StoreTemplates::get($slug);
            if ($store === null) {
                $this->problem("{$where}/store.json is missing, not valid JSON, or not format " . StoreTemplates::FORMAT . '.');
                continue;
            }
            if (trim((string) ($store['line'] ?? '')) === '') {
                $this->problem("{$where}/store.json has no line for the picker card.");
            }
            if (! is_file("{$dir}/thumbnail.jpg")) {
                $this->problem("{$where}/thumbnail.jpg is missing.");
            }
            if (array_key_exists('samples', $store)) {
                $this->problem("{$where}/store.json lists sample products; a starting store brings no products.");
            }

            $settings = (array) $store['documents']['settings'];
            $extra = array_diff(array_keys((array) ($settings['values'] ?? [])), StoreTemplates::lookKeys());
            if ($extra !== []) {
                $this->problem("{$where}: the settings may only hold the look; not " . implode(', ', $extra) . '.');
            }
            foreach (ContrastCheck::failures((array) ($settings['schemes'] ?? [])) as $failure) {
                $this->problem("{$where}: color scheme " . ($failure['scheme'] ?? '?') . ' is hard to read (' . ($failure['pair'] ?? 'text') . ').');
            }
            $schemeIds = array_column((array) ($settings['schemes'] ?? []), 'id');
            $this->lintDocument($where, Handles::SETTINGS, $settings + ['based_on' => ['look' => $slug, 'sections' => $slug]], $schemeIds);

            $used = [];
            $ids = [];
            foreach (StoreTemplates::SECTION_HANDLES as $handle) {
                $content = $store['documents'][$handle] ?? null;
                if (! is_array($content)) {
                    $this->problem("{$where}: the {$handle} document is missing.");
                    continue;
                }
                foreach (array_keys((array) ($content['sections'] ?? [])) as $id) {
                    if (isset($ids[$id])) {
                        $this->problem("{$where}: the section id {$id} is used in both {$ids[$id]} and {$handle}.");
                    }
                    $ids[$id] = $handle;
                }
                foreach (Portable::references($content) as [$record, $ref]) {
                    if (preg_match(Portable::SLUG, $ref) !== 1) {
                        $this->problem("{$where}/{$handle}: \"{$ref}\" is not a {$record} slug.");
                    }
                }
                foreach (Portable::photos($content) as $photo) {
                    $used[] = $this->storePhoto($where, $handle, $photo);
                }
                // Every slug stands in for some record here: the records are the merchant's.
                $portable = new Portable($slug, fn (string $record, array $slugs) => array_combine($slugs, range(1, count($slugs))));
                $this->lintDocument($where, $handle, $portable->resolve($content)['content'], $schemeIds);
            }

            // The fresh install copies the photos the default pages and the section presets show.
            foreach ($this->installedPhotos($slug) as $source => $names) {
                foreach ($names as $name) {
                    if (! is_file("{$dir}/images/{$name}")) {
                        $this->problem("{$source} shows media/xelga/{$slug}/{$name}, which {$where}/images does not ship.");
                    }
                    $used[] = $name;
                }
            }
            foreach (glob("{$dir}/images/*") ?: [] as $file) {
                if (! in_array(basename($file), $used, true)) {
                    $this->problem("{$where}/images/" . basename($file) . ' is not used.');
                }
                if (filesize($file) > 300 * 1024) {
                    $this->problem("{$where}/images/" . basename($file) . ' is larger than 300 KB.');
                }
            }
        }
    }

    /**
     * The photos of one store the shipped defaults and the section presets
     * name as media library paths, by where they are named.
     *
     * @return array<string, list<string>>
     */
    private function installedPhotos(string $slug): array
    {
        $sources = [];
        foreach (Defaults::handles() as $handle) {
            $sources["store-templates/_defaults/{$handle}.json"] = Defaults::shipped($handle);
        }
        foreach (Catalog::sections() as $type => $spec) {
            $sources["schemas/sections/{$type}.json presets"] = (array) ($spec['presets'] ?? []);
        }
        $out = [];
        foreach ($sources as $source => $content) {
            preg_match_all('#media/xelga/' . preg_quote($slug, '#') . '/([a-z0-9._-]+)#', (string) json_encode($content, JSON_UNESCAPED_SLASHES), $m);
            if ($m[1] !== []) {
                $out[$source] = array_values(array_unique($m[1]));
            }
        }

        return $out;
    }

    /** A photo a store names must be images/{file} and ship with it; answers the file name. */
    private function storePhoto(string $where, string $part, string $photo): string
    {
        if (preg_match(StoreTemplates::IMAGE, $photo, $m) !== 1) {
            $this->problem("{$where}/{$part}: the photo {$photo} must be images/ and a file name.");

            return '';
        }
        if (! is_file(Catalog::root("{$where}/images/{$m[1]}"))) {
            $this->problem("{$where}/{$part}: {$photo} is missing.");
        }

        return $m[1];
    }

    /** @param list<string> $schemeIds */
    private function lintDocument(string $where, string $handle, array $content, array $schemeIds): void
    {
        try {
            $result = (new Shape(SanitizeContext::forLint($schemeIds !== [] ? $schemeIds : ['scheme-1'])))->normalize($handle, $content);
            foreach ($result['adjusted'] as $adjusted) {
                $this->problem("{$where}/{$handle}: {$adjusted['path']}: {$adjusted['reason']}");
            }
        } catch (ShapeException $e) {
            foreach ($e->errors as $path => $messages) {
                $this->problem("{$where}/{$handle}: {$path}: " . implode(' ', $messages));
            }
        }
    }

    /** css/generated/ranges.css must hold a rule for every range value. */
    private function ranges(): void
    {
        $css = (string) @file_get_contents(Catalog::root('css/generated/ranges.css'));
        foreach (Catalog::sections() as $type => $spec) {
            // An extension writes its own rules into its own stylesheet.
            if (isset($spec['source'])) {
                continue;
            }
            foreach ((array) $spec['settings'] as $raw) {
                if (($raw['type'] ?? null) !== 'range') {
                    continue;
                }
                $setting = new SettingSchema($raw);
                $attr = str_replace('_', '-', $setting->key);
                foreach ($setting->rangeValues() as $value) {
                    if (! str_contains($css, "[data-{$attr}=\"{$value}\"]{--{$attr}:{$value}}")) {
                        $this->problem("css/generated/ranges.css is out of date for {$type}.{$setting->key}; run node bin/ranges.mjs.");
                        break 2;
                    }
                }
            }
        }
    }

    /** css/generated/swatches.css must match the color words the cards know. */
    private function swatches(): void
    {
        $css = (string) @file_get_contents(Catalog::root('css/generated/swatches.css'));
        if ($css !== \Themes\Xelga\Sections\Swatch::css()) {
            $this->problem('css/generated/swatches.css is out of date; write it again from Themes\\Xelga\\Sections\\Swatch::css().');
        }
    }

    private function problem(string $text): void
    {
        $this->problems[] = $text;
    }
}
