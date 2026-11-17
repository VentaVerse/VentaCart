<?php

namespace Themes\Xelga\Documents;

use App\Support\SafeInput;
use Themes\Xelga\Catalog\Catalog;
use Themes\Xelga\Settings\SanitizeContext;
use Themes\Xelga\Settings\Sanitizer;
use Themes\Xelga\StoreTemplates\StoreTemplates;

/**
 * The structure of a document's JSON: ids, order lists, section and block
 * types, counts and limits. Structural problems are refused with the path
 * of each one; values inside the structure go through the Sanitizer, which
 * corrects them and reports what it changed.
 */
final class Shape
{
    public const FORMAT = 1;

    public const MAX_SECTIONS = 40;

    public const MAX_BYTES = 262144;

    public const ID = '/^[a-z0-9-]{1,40}$/';

    public const SCHEME_ID = '/^scheme-[1-9][0-9]?$/';

    /** The most a dormant section (one whose extension is away) may hold, as stored. */
    public const MAX_DORMANT_BYTES = 16384;

    /** @var array<string, list<string>> */
    private array $errors = [];

    /** @var array<string, true> every id seen in this document */
    private array $ids = [];

    private Sanitizer $sanitizer;

    public function __construct(private readonly SanitizeContext $context)
    {
        $this->sanitizer = new Sanitizer($context);
    }

    /**
     * The clean document and the values that were corrected.
     *
     * @return array{content: array, adjusted: list<array>}
     *
     * @throws ShapeException
     */
    public function normalize(string $handle, mixed $content): array
    {
        $this->errors = [];
        $this->ids = [];
        $this->sanitizer = new Sanitizer($this->context);

        if (! is_array($content) || ($content !== [] && array_is_list($content))) {
            throw new ShapeException(['content' => ['The document must be an object.']]);
        }

        if (strlen((string) json_encode($content)) > self::MAX_BYTES) {
            throw new ShapeException(['content' => ['The document is larger than 256 KB.']]);
        }

        $format = $content['schema'] ?? self::FORMAT;
        if ($format !== self::FORMAT) {
            $this->fail('content.schema', 'Unknown document format.');
        }

        $clean = Handles::kind($handle) === 'settings'
            ? $this->settings($content)
            : $this->sections($handle, $content);

        if ($this->errors !== []) {
            throw new ShapeException($this->errors);
        }

        return ['content' => $clean, 'adjusted' => $this->sanitizer->adjusted()];
    }

    private function settings(array $content): array
    {
        $values = $this->sanitizer->cleanMap(Catalog::themeSettings(), $content['values'] ?? [], 'content.values');

        $setting = Catalog::schemeSetting();
        $min = (int) ($setting->raw['min'] ?? 3);
        $max = (int) ($setting->raw['max'] ?? 8);
        $roles = Catalog::schemeRoles();
        $schemes = $content['schemes'] ?? null;

        if (! is_array($schemes) || ! array_is_list($schemes)) {
            $this->fail('content.schemes', 'The color schemes must be a list.');

            return ['schema' => self::FORMAT, 'values' => $values, 'schemes' => []];
        }
        if (count($schemes) < $min || count($schemes) > $max) {
            $this->fail('content.schemes', "A store has {$min} to {$max} color schemes.");
        }

        $out = [];
        $seen = [];
        foreach ($schemes as $i => $scheme) {
            $path = "content.schemes.{$i}";
            if (! is_array($scheme)) {
                $this->fail($path, 'A color scheme must be an object.');
                continue;
            }
            $id = $scheme['id'] ?? null;
            if (! is_string($id) || preg_match(self::SCHEME_ID, $id) !== 1) {
                $this->fail("{$path}.id", 'A color scheme id is scheme- followed by a number.');
                continue;
            }
            if (isset($seen[$id])) {
                $this->fail("{$path}.id", "The color scheme id {$id} is used twice.");
                continue;
            }
            $seen[$id] = true;

            $colors = [];
            foreach ($roles as $role) {
                $color = SafeInput::color($scheme['colors'][$role] ?? null);
                if (preg_match('/^#[0-9a-f]{3}$/', $color) === 1) {
                    $color = '#' . $color[1] . $color[1] . $color[2] . $color[2] . $color[3] . $color[3];
                }
                if (preg_match('/^#[0-9a-f]{6}$/', $color) !== 1) {
                    $this->fail("{$path}.colors.{$role}", 'A color is a 6-digit hex value such as #1f4fa3.');
                    continue;
                }
                $colors[$role] = $color;
            }

            $label = trim(SafeInput::text($scheme['label'] ?? '', 40));
            $out[] = ['id' => $id, 'label' => $label !== '' ? $label : 'Scheme ' . substr($id, 7), 'colors' => $colors];
        }

        $clean = ['schema' => self::FORMAT, 'values' => $values, 'schemes' => $out];

        // The starting store the look and the sections came from, for the
        // editor's "Based on" row. Only names of shipped stores are kept.
        $based = $content['based_on'] ?? null;
        if (is_array($based) && StoreTemplates::exists($based['look'] ?? null) && StoreTemplates::exists($based['sections'] ?? null)) {
            $clean['based_on'] = ['look' => $based['look'], 'sections' => $based['sections']];
        }

        return $clean;
    }

    private function sections(string $handle, array $content): array
    {
        $kind = Handles::kind($handle);
        $pageType = Handles::pageType($handle);
        $order = $content['order'] ?? [];
        $sections = $content['sections'] ?? [];

        if (! is_array($order) || ! array_is_list($order)) {
            $this->fail('content.order', 'The section order must be a list.');
            $order = [];
        }
        if (! is_array($sections) || ($sections !== [] && array_is_list($sections))) {
            $this->fail('content.sections', 'The sections must be an object keyed by id.');
            $sections = [];
        }
        if (count($order) > self::MAX_SECTIONS) {
            $this->fail('content.order', 'A page holds at most ' . self::MAX_SECTIONS . ' sections.');
        }

        $this->sameKeys('content', $order, $sections, 'section');

        $perType = [];
        $outSections = [];
        foreach ($order as $id) {
            if (! is_string($id) || ! isset($sections[$id]) || ! is_array($sections[$id])) {
                continue;
            }
            $path = "content.sections.{$id}";
            $section = $sections[$id];
            $type = $section['type'] ?? null;
            if (! is_string($type) || $type === '') {
                $this->fail("{$path}.type", 'A section needs a type.');
                continue;
            }

            $schema = Catalog::section($type);
            $label = trim(SafeInput::text($section['label'] ?? '', 80));
            $disabled = $this->flag($section['disabled'] ?? false, "{$path}.disabled");

            if ($schema === null && ($extension = self::extensionOf($type)) !== null) {
                // An extension's section while the extension is switched off
                // or removed: kept exactly as stored, settings and hidden
                // flag included, so switching the extension back on brings it
                // back as it was. Nothing checks its values now, so it is
                // marked, and the renderer cleans them before any view sees
                // them; the next save after the extension returns cleans it.
                $dormant = $this->dormant($section, $extension, $label, $disabled, $path);
                if ($dormant !== null) {
                    $outSections[$id] = $dormant;
                }
                continue;
            }

            if ($schema === null) {
                // Kept so the editor can show it with Remove; its values are
                // not kept, since nothing can check them. Only a type that
                // could be a section's name is kept: anything else is not a
                // section from any version of the theme.
                if (preg_match(self::ID, $type) !== 1) {
                    $this->fail("{$path}.type", 'Not a section type: 1 to 40 lowercase letters, digits or hyphens.');
                    continue;
                }
                $outSections[$id] = ['type' => $type, 'label' => $label, 'disabled' => true, 'settings' => (object) [], 'block_order' => [], 'blocks' => (object) []];
                continue;
            }

            if (! $schema->allowedIn($handle, $kind, $pageType)) {
                $this->fail("{$path}.type", "{$schema->label} cannot be added to this page.");
                continue;
            }

            $perType[$type] = ($perType[$type] ?? 0) + 1;
            if ($schema->limit() !== null && $perType[$type] > $schema->limit()) {
                $this->fail("{$path}.type", "A page holds at most {$schema->limit()} {$schema->label}.");
            }

            $outSections[$id] = [
                'type' => $type,
                'label' => $label !== '' ? $label : $schema->label,
                'disabled' => $disabled,
                'settings' => (object) $this->sanitizer->cleanMap($schema->settings(), $section['settings'] ?? [], "{$path}.settings"),
            ] + $this->blocks($schema, $section, $path);
            if ($schema->source() !== null) {
                $outSections[$id]['source'] = $schema->source();
            }
        }

        if ($kind === 'template' && $pageType !== null) {
            $this->mainSections($pageType, $outSections);
        }

        return ['schema' => self::FORMAT, 'order' => array_values(array_filter($order, 'is_string')), 'sections' => (object) $outSections];
    }

    /**
     * A page type's main section (product information on the product page,
     * the results on the search page) is what the page is for: a template
     * of that page type, custom ones included, keeps exactly one of it and
     * never hides it. Its blocks can still be hidden and reordered. Which
     * types are main for a page comes from the shipped default template.
     *
     * @param  array<string, array>  $sections  the cleaned sections
     */
    private function mainSections(string $pageType, array $sections): void
    {
        $required = [];
        foreach ((array) (Defaults::content($pageType)['sections'] ?? []) as $section) {
            $schema = Catalog::section((string) (is_array($section) ? ($section['type'] ?? '') : ''));
            if ($schema !== null && ($schema->raw['main'] ?? false) === true) {
                $required[$schema->type] = $schema;
            }
        }

        foreach ($required as $type => $schema) {
            $found = array_filter($sections, fn ($s) => ($s['type'] ?? null) === $type);
            if ($found === []) {
                $this->fail('content.order', "{$schema->label} is the page's main section and cannot be removed.");
                continue;
            }
            if (count($found) > 1) {
                $this->fail('content.order', "A page holds one {$schema->label}.");
            }
            foreach ($found as $id => $section) {
                if ($section['disabled'] === true) {
                    $this->fail("content.sections.{$id}.disabled", "{$schema->label} is the page's main section and cannot be hidden. Hide its blocks instead.");
                }
            }
        }
    }

    private function blocks(\Themes\Xelga\Catalog\SectionSchema $schema, array $section, string $path): array
    {
        $order = $section['block_order'] ?? [];
        $blocks = $section['blocks'] ?? [];
        if (! is_array($order) || ! array_is_list($order)) {
            $this->fail("{$path}.block_order", 'The block order must be a list.');
            $order = [];
        }
        if (! is_array($blocks) || ($blocks !== [] && array_is_list($blocks))) {
            $this->fail("{$path}.blocks", 'The blocks must be an object keyed by id.');
            $blocks = [];
        }

        $this->sameKeys($path, $order, $blocks, 'block');

        if (count($order) < $schema->blockMin()) {
            $this->fail("{$path}.block_order", "{$schema->label} needs at least {$schema->blockMin()}.");
        }
        if (count($order) > $schema->blockMax()) {
            $this->fail("{$path}.block_order", $schema->blockMax() === 0 ? "{$schema->label} has no blocks." : "{$schema->label} holds at most {$schema->blockMax()}.");
        }

        $perType = [];
        $out = [];
        foreach ($order as $id) {
            if (! is_string($id) || ! isset($blocks[$id]) || ! is_array($blocks[$id])) {
                continue;
            }
            $bPath = "{$path}.blocks.{$id}";
            $type = $blocks[$id]['type'] ?? null;
            if (! is_string($type) || preg_match(self::ID, $type) !== 1) {
                $this->fail("{$bPath}.type", 'Not a block type: 1 to 40 lowercase letters, digits or hyphens.');
                continue;
            }
            // The section's schema is known here, so a block type it does
            // not hold is refused rather than kept.
            $spec = $schema->blockType($type);
            if ($spec === null) {
                $this->fail("{$bPath}.type", "Not a kind of block {$schema->label} holds.");
                continue;
            }
            $perType[$type] = ($perType[$type] ?? 0) + 1;
            if (isset($spec['limit']) && $spec['limit'] !== null && $perType[$type] > (int) $spec['limit']) {
                $this->fail("{$bPath}.type", "{$schema->label} holds at most {$spec['limit']} {$spec['label']}.");
            }
            $label = trim(SafeInput::text($blocks[$id]['label'] ?? '', 80));
            $out[$id] = [
                'type' => $type,
                'label' => $label !== '' ? $label : (string) $spec['label'],
                'disabled' => $this->flag($blocks[$id]['disabled'] ?? false, "{$bPath}.disabled"),
                'settings' => (object) $this->sanitizer->cleanMap(
                    $schema->blockSettings($type),
                    $schema->carryDown($type, (array) ($section['settings'] ?? []), (array) ($blocks[$id]['settings'] ?? [])),
                    "{$bPath}.settings",
                ),
            ];
        }

        return ['block_order' => array_values(array_filter($order, 'is_string')), 'blocks' => (object) $out];
    }

    /** The extension an extension type belongs to (`marketplace.seller-card`), or null for any other type. */
    public static function extensionOf(string $type): ?string
    {
        return preg_match(Catalog::EXTENSION_TYPE, $type, $m) === 1 && strlen($m[2]) <= 40 ? $m[1] : null;
    }

    /** Whether a stored section is an extension section kept while its extension is away. */
    public static function isDormant(mixed $section): bool
    {
        return is_array($section)
            && is_string($section['type'] ?? null) && self::extensionOf($section['type']) !== null
            && Catalog::section($section['type']) === null;
    }

    /**
     * A dormant section as stored: its ids are still checked, its values
     * are kept unread. The source is the type's own prefix, never what the
     * client sent.
     */
    private function dormant(array $section, string $extension, string $label, bool $disabled, string $path): ?array
    {
        if (strlen((string) json_encode($section)) > self::MAX_DORMANT_BYTES) {
            $this->fail($path, 'A section kept while its extension is away holds at most 16 KB.');

            return null;
        }

        $order = $section['block_order'] ?? [];
        $blocks = $section['blocks'] ?? [];
        if (! is_array($order) || ! array_is_list($order)) {
            $this->fail("{$path}.block_order", 'The block order must be a list.');
            $order = [];
        }
        if (! is_array($blocks) || ($blocks !== [] && array_is_list($blocks))) {
            $this->fail("{$path}.blocks", 'The blocks must be an object keyed by id.');
            $blocks = [];
        }
        $this->sameKeys($path, $order, $blocks, 'block');

        $out = [];
        foreach ($order as $id) {
            $block = is_string($id) ? ($blocks[$id] ?? null) : null;
            if (! is_array($block)) {
                continue;
            }
            $type = $block['type'] ?? null;
            if (! is_string($type) || (preg_match(self::ID, $type) !== 1 && self::extensionOf($type) === null)) {
                $this->fail("{$path}.blocks.{$id}.type", 'Not a block type.');
                continue;
            }
            $out[$id] = [
                'type' => $type,
                'label' => trim(SafeInput::text($block['label'] ?? '', 80)),
                'disabled' => $this->flag($block['disabled'] ?? false, "{$path}.blocks.{$id}.disabled"),
                'settings' => (object) self::storedMap($block['settings'] ?? []),
            ];
        }

        return [
            'type' => (string) $section['type'],
            'label' => $label,
            'disabled' => $disabled,
            'settings' => (object) self::storedMap($section['settings'] ?? []),
            'block_order' => array_values(array_filter($order, 'is_string')),
            'blocks' => (object) $out,
            'source' => $extension,
            'unchecked' => true,
        ];
    }

    /** Stored values kept unread: a map, or nothing. */
    private static function storedMap(mixed $values): array
    {
        $values = is_object($values) ? (array) $values : $values;

        return is_array($values) && ($values === [] || ! array_is_list($values)) ? $values : [];
    }

    /** The order list and the map must name the same ids, each once, each well formed and new to the document. */
    private function sameKeys(string $path, array $order, array $map, string $what): void
    {
        $listed = [];
        foreach ($order as $i => $id) {
            if (! is_string($id) || preg_match(self::ID, $id) !== 1) {
                $this->fail("{$path}.order.{$i}", "A {$what} id is 1 to 40 lowercase letters, digits or hyphens.");
                continue;
            }
            if (isset($listed[$id]) || isset($this->ids[$id])) {
                $this->fail("{$path}.order.{$i}", "The id {$id} is used twice.");
                continue;
            }
            $listed[$id] = true;
            $this->ids[$id] = true;
            if (! array_key_exists($id, $map)) {
                $this->fail("{$path}.order.{$i}", "The {$what} {$id} is listed but missing.");
            }
        }
        foreach (array_keys($map) as $id) {
            if (! isset($listed[(string) $id])) {
                $this->fail($path, "The {$what} {$id} is not in the order list.");
            }
        }
    }

    private function flag(mixed $value, string $path): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        $this->fail($path, 'Must be true or false.');

        return false;
    }

    private function fail(string $path, string $message): void
    {
        $this->errors[$path][] = $message;
    }
}
