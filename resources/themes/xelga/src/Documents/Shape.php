<?php

namespace Themes\Xelga\Documents;

use App\Support\SafeInput;
use Themes\Xelga\Catalog\Catalog;
use Themes\Xelga\Catalog\SettingSchema;
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

    /** @var array<string, array<string, true>> section id => types it has in a stored document */
    private array $storedSections = [];

    /** @var array<string, array<string, array<string, true>>> section id => block id => types */
    private array $storedBlocks = [];

    /** @var list<array{path: string, reason: string, value: string}> sections Layout::normalize moved */
    private array $moved = [];

    private Sanitizer $sanitizer;

    public function __construct(private readonly SanitizeContext $context)
    {
        $this->sanitizer = new Sanitizer($context);
    }

    /**
     * The clean document and the values that were corrected.
     *
     * An extension section or block whose extension is away can only be
     * kept, never added: it stays when one of the stored documents (the
     * draft and the published version being replaced, the revision being
     * restored) already holds that id with that type. Anything else of an
     * absent extension is refused, since nothing can check where it goes.
     *
     * @param  list<mixed>  $stored  documents whose dormant items may stay
     * @return array{content: array, adjusted: list<array>}
     *
     * @throws ShapeException
     */
    public function normalize(string $handle, mixed $content, array $stored = []): array
    {
        $this->errors = [];
        $this->ids = [];
        $this->moved = [];
        $this->sanitizer = new Sanitizer($this->context);
        $this->remember($stored);

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

        return ['content' => $clean, 'adjusted' => [...$this->sanitizer->adjusted(), ...$this->moved]];
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

            // Extension sections go on pages only; the header and the footer
            // are drawn on every page, checkout and account included.
            if ($kind !== 'template' && self::extensionOf($type) !== null) {
                $this->fail("{$path}.type", 'An extension\'s section goes on a page, not in the header or footer.');
                continue;
            }

            $schema = Catalog::section($type);
            $label = trim(SafeInput::text($section['label'] ?? '', 80));
            $disabled = $this->flag($section['disabled'] ?? false, "{$path}.disabled");

            if ($schema === null && ($extension = self::extensionOf($type)) !== null) {
                if (! isset($this->storedSections[$id][$type])) {
                    $this->fail("{$path}.type", 'This section\'s extension is not available.');
                    continue;
                }
                // An extension's section while the extension is switched off
                // or removed: kept exactly as stored, settings and hidden
                // flag included, so switching the extension back on brings it
                // back as it was. Nothing checks its values now, so it is
                // marked, and the renderer cleans them before any view sees
                // them; the next save after the extension returns cleans it.
                $dormant = $this->dormant($id, $section, $extension, $label, $disabled, $path);
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
            ] + $this->blocks($id, $schema, $section, $path);
            if ($schema->source() !== null) {
                $outSections[$id]['source'] = $schema->source();
            }
        }

        if ($kind === 'template' && $pageType !== null) {
            $this->mainSections($pageType, $outSections);
        }

        $layout = $this->layout($handle, $content, array_keys($outSections));

        $clean = ['schema' => self::FORMAT, 'order' => array_values(array_filter($order, 'is_string')), 'sections' => (object) $outSections];
        // The extension placements this template has been given (Placements).
        $placed = \Themes\Xelga\Extensions\Placements::placed($content['placed'] ?? []);
        if ($kind === 'template' && $placed !== []) {
            $clean['placed'] = $placed;
        }

        if ($layout === [] || $this->errors !== []) {
            return $clean;
        }

        // Rows and sides decide the reading order; what does not fit where
        // it was put moves to a row of its own and is reported.
        $result = Layout::normalize($clean + $layout, app(LayoutRules::class, ['handle' => $handle]));
        array_push($this->moved, ...$result['moved']);

        return $result['content'];
    }

    /**
     * The document's rows and sides, checked for structure. Problems are
     * refused with their path; row settings are cleaned like any setting.
     * Ids that name no section are left to Layout::normalize, which drops
     * them, so a copy that leaves sections out keeps the rest of its rows.
     *
     * @param  list<string>  $sectionIds
     * @return array{rows?: list<array>, sides?: array<string, list<string>>}
     */
    private function layout(string $handle, array $content, array $sectionIds): array
    {
        $layout = [];
        $placed = [];
        $taken = array_fill_keys(array_map('strval', $sectionIds), true);
        $place = function (mixed $id, string $path) use (&$placed): ?string {
            if (! is_string($id) || preg_match(self::ID, $id) !== 1) {
                $this->fail($path, 'A section id is 1 to 40 lowercase letters, digits or hyphens.');

                return null;
            }
            if (isset($placed[$id])) {
                $this->fail($path, "The section {$id} is placed twice.");

                return null;
            }
            $placed[$id] = true;

            return $id;
        };

        // Sides first: a section in a side and in a row is reported in the row.
        $sides = $content['sides'] ?? null;
        if ($sides !== null) {
            if (! Layout::sidesAllowed($handle)) {
                // Empty sides say nothing; any section in one is refused.
                if (self::holdsIds($sides)) {
                    $this->fail('content.sides', 'This page cannot have sides.');
                }
            } elseif (! is_array($sides) || ($sides !== [] && array_is_list($sides)) || array_diff(array_keys($sides), Layout::SIDES) !== []) {
                $this->fail('content.sides', 'The sides are an object with a left and a right list.');
            } else {
                $out = [];
                foreach (Layout::SIDES as $which) {
                    $ids = $sides[$which] ?? [];
                    $path = "content.sides.{$which}";
                    if (! is_array($ids) || ! array_is_list($ids)) {
                        $this->fail($path, 'A side is a list of section ids.');
                        continue;
                    }
                    if (count($ids) > Layout::MAX_PER_SIDE) {
                        $this->fail($path, 'A side holds at most ' . Layout::MAX_PER_SIDE . ' sections.');
                        continue;
                    }
                    foreach ($ids as $k => $id) {
                        if (($id = $place($id, "{$path}.{$k}")) !== null) {
                            $out[$which][] = $id;
                        }
                    }
                }
                $layout['sides'] = $out;
            }
        }

        $rows = $content['rows'] ?? null;
        if ($rows === null) {
            return $layout;
        }
        if (! is_array($rows) || ! array_is_list($rows)) {
            $this->fail('content.rows', 'The rows must be a list.');

            return $layout;
        }
        if (count($rows) > Layout::MAX_ROWS) {
            $this->fail('content.rows', 'A page holds at most ' . Layout::MAX_ROWS . ' rows.');

            return $layout;
        }

        $settingsSchema = $this->rowSettings();
        $out = [];
        foreach ($rows as $i => $row) {
            $path = "content.rows.{$i}";
            if (! is_array($row) || ($row !== [] && array_is_list($row))) {
                $this->fail($path, 'A row must be an object.');
                continue;
            }

            $id = $row['id'] ?? null;
            if ($id !== null) {
                if (! is_string($id) || preg_match(self::ID, $id) !== 1) {
                    $this->fail("{$path}.id", 'A row id is 1 to 40 lowercase letters, digits or hyphens.');
                } elseif (isset($taken[$id])) {
                    $this->fail("{$path}.id", "The id {$id} is used twice.");
                } else {
                    $taken[$id] = true;
                }
            }

            $split = $row['split'] ?? null;
            $split = is_int($split) ? (string) $split : $split;
            if (! is_string($split) || ! isset(Layout::SPLITS[$split])) {
                $this->fail("{$path}.split", 'Not one of the column choices.');
                continue;
            }
            $parts = Layout::SPLITS[$split]['parts'];

            $columns = $row['columns'] ?? null;
            if (! is_array($columns) || ! array_is_list($columns) || count($columns) !== count($parts)) {
                $this->fail("{$path}.columns", 'This row has ' . count($parts) . ' ' . (count($parts) === 1 ? 'column.' : 'columns.'));
                continue;
            }

            $cleanColumns = [];
            foreach ($columns as $c => $column) {
                $cPath = "{$path}.columns.{$c}";
                if (! is_array($column) || ! array_is_list($column)) {
                    $this->fail($cPath, 'A column is a list of section ids.');
                    continue 2;
                }
                if ($split === '12' && count($column) !== 1) {
                    $this->fail($cPath, 'A one-column row holds one section.');
                    continue 2;
                }
                if (count($column) > Layout::MAX_PER_COLUMN) {
                    $this->fail($cPath, 'A column holds at most ' . Layout::MAX_PER_COLUMN . ' sections.');
                    continue 2;
                }
                $ids = [];
                foreach ($column as $k => $sid) {
                    if (($sid = $place($sid, "{$cPath}.{$k}")) !== null) {
                        $ids[] = $sid;
                    }
                }
                $cleanColumns[] = $ids;
            }

            // An emptied value arrives as null (the framework turns '' into
            // null), which for a row means its default: no scheme, page width.
            $settings = $row['settings'] ?? [];
            $settings = is_array($settings) ? array_filter($settings, fn ($value) => $value !== null) : $settings;
            $clean = ['split' => $split, 'columns' => $cleanColumns, 'settings' => $this->sanitizer->cleanMap($settingsSchema, $settings, "{$path}.settings")];
            if (is_string($id)) {
                $clean = ['id' => $id] + $clean;
            }
            $out[] = $clean;
        }
        $layout['rows'] = $out;

        return $layout;
    }

    /** Whether a sides value names anything: an empty object, or empty lists, does not. */
    private static function holdsIds(mixed $sides): bool
    {
        if (! is_array($sides)) {
            return true;
        }
        foreach ($sides as $ids) {
            if (! is_array($ids) || $ids !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * A row's settings as setting schemas, so they are cleaned and reported
     * like a section's: width, color scheme (or none) and the space between
     * columns, with the defaults Layout gives.
     *
     * @return array<string, SettingSchema>
     */
    private function rowSettings(): array
    {
        $defaults = Layout::ROW_DEFAULTS;
        $select = fn (string $key, array $values) => new SettingSchema(['key' => $key, 'type' => 'select', 'default' => $defaults[$key], 'options' => $values]);

        return [
            'width' => $select('width', Layout::ROW_CHOICES['width']),
            'scheme' => $select('scheme', ['', ...$this->context->schemeIds]),
            'space' => $select('space', Layout::ROW_CHOICES['space']),
        ];
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
        foreach (Defaults::mainTypes($pageType) as $type) {
            $required[$type] = Catalog::section($type);
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

    private function blocks(string $sectionId, \Themes\Xelga\Catalog\SectionSchema $schema, array $section, string $path): array
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

        // Blocks of an extension that is away, kept from a stored document,
        // take no room; any other dormant block is refused below.
        $live = count(array_filter($order, fn ($id) => ! (is_string($id) && is_array($blocks[$id] ?? null)
            && self::isDormantBlock($blocks[$id]) && $this->wasStoredBlock($sectionId, $id, (string) $blocks[$id]['type']))));
        if ($live < $schema->blockMin()) {
            $this->fail("{$path}.block_order", "{$schema->label} needs at least {$schema->blockMin()}.");
        }
        if ($live > $schema->blockMax()) {
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
            if (! is_string($type) || (preg_match(self::ID, $type) !== 1 && self::extensionOf($type) === null)) {
                $this->fail("{$bPath}.type", 'Not a block type: 1 to 40 lowercase letters, digits or hyphens.');
                continue;
            }
            if (self::isDormantBlock($blocks[$id])) {
                if (! $this->wasStoredBlock($sectionId, $id, $type)) {
                    $this->fail("{$bPath}.type", 'This block\'s extension is not available.');
                    continue;
                }
                $dormant = $this->dormantBlock($blocks[$id], $bPath);
                if ($dormant !== null) {
                    $out[$id] = $dormant;
                }
                continue;
            }
            // The section's schema is known here, so a block type it does
            // not hold (an extension block it does not take, too) is refused
            // rather than kept.
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
            if (is_string($spec['source'] ?? null)) {
                $out[$id]['source'] = $spec['source'];
            }
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
    private function dormant(string $sectionId, array $section, string $extension, string $label, bool $disabled, string $path): ?array
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
            // Nothing checks a dormant section's blocks, so it keeps only those it had.
            if (! $this->wasStoredBlock($sectionId, $id, $type)) {
                $this->fail("{$path}.blocks.{$id}.type", 'A block cannot be added while its section\'s extension is not available.');
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

    /** Whether a stored block is an extension block kept while no registered extension has its type. */
    public static function isDormantBlock(array $block): bool
    {
        $type = $block['type'] ?? null;

        return is_string($type) && self::extensionOf($type) !== null && Catalog::extensionBlockSpec($type) === null;
    }

    /** A dormant block as stored, like a dormant section: values kept unread, marked for cleaning. */
    private function dormantBlock(array $block, string $path): ?array
    {
        if (strlen((string) json_encode($block)) > self::MAX_DORMANT_BYTES) {
            $this->fail($path, 'A block kept while its extension is away holds at most 16 KB.');

            return null;
        }

        return [
            'type' => (string) $block['type'],
            'label' => trim(SafeInput::text($block['label'] ?? '', 80)),
            'disabled' => $this->flag($block['disabled'] ?? false, "{$path}.disabled"),
            'settings' => (object) self::storedMap($block['settings'] ?? []),
            'source' => (string) self::extensionOf((string) $block['type']),
            'unchecked' => true,
        ];
    }

    /**
     * Index the ids and types of the stored documents, so a dormant item can
     * be told apart from one the client made up.
     *
     * @param  list<mixed>  $documents
     */
    private function remember(array $documents): void
    {
        $this->storedSections = [];
        $this->storedBlocks = [];
        foreach ($documents as $document) {
            if (is_object($document)) {
                $document = json_decode((string) json_encode($document), true);
            }
            if (! is_array($document) || ! is_array($document['sections'] ?? null)) {
                continue;
            }
            foreach ($document['sections'] as $sid => $section) {
                if (! is_array($section) || ! is_string($section['type'] ?? null)) {
                    continue;
                }
                $this->storedSections[(string) $sid][$section['type']] = true;
                foreach ((array) ($section['blocks'] ?? []) as $bid => $block) {
                    if (is_array($block) && is_string($block['type'] ?? null)) {
                        $this->storedBlocks[(string) $sid][(string) $bid][$block['type']] = true;
                    }
                }
            }
        }
    }

    private function wasStoredBlock(string $sectionId, string $blockId, string $type): bool
    {
        return isset($this->storedBlocks[$sectionId][$blockId][$type]);
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
