<?php

namespace Themes\Xelga\Rendering;

use Illuminate\Support\HtmlString;
use Themes\Xelga\Catalog\Catalog;
use Themes\Xelga\Documents\Defaults;
use Themes\Xelga\Documents\Handles;
use Themes\Xelga\Documents\Layout;
use Themes\Xelga\Documents\LayoutRules;
use Themes\Xelga\Documents\Shape;

/**
 * Draws a document's sections in order, row by row (see Layout). Settings are merged over the
 * schema's defaults, so a setting added in a theme update needs no data
 * change. Each section is wrapped in comment markers, and one that fails
 * is logged and left out, so a bad section never takes the page down.
 */
final class SectionRenderer
{
    /** @var list<array> what the editor preview needs to know about the drawn sections */
    private array $pageData = [];

    /**
     * The extension sections of the document being drawn that may be drawn
     * where they sit, decided once in reading order (see placeableIds()),
     * so drawing the sides before the rows changes no limit.
     *
     * @var array<string, true>
     */
    private array $placeableIds = [];

    /** How wide the page's content area was drawn, in twelfths of the page. */
    private int $contentSpan = 12;

    /**
     * Draws a document. For a page template, the context's span is the
     * width of the page (12). Its sides are drawn first, three twelfths
     * each, since only what they show decides how wide Content is. When a
     * side shows something, the page reads Top, then a wrapper holding the
     * left side, Content (drawn at the width the sides leave) and the right
     * side, then Bottom; Top and Bottom stay the whole page wide. When no
     * side shows anything, the rows of every area are drawn one after
     * another, the whole page wide, with no wrapper. Header and footer are
     * always the whole page wide.
     */
    public function render(array $content, RenderContext $context): string
    {
        $sections = (array) ($content['sections'] ?? []);
        $rows = $this->rowsOf($content, $context);
        $this->placeableIds = $this->placeableIds($rows, $content, $context);
        $firstIds = $this->drawnIds($rows, $content, $context);
        $context = $context->withTypes(array_keys($firstIds), $firstIds);

        if ($context->group !== 'template') {
            $html = '';
            foreach ($rows as $row) {
                $html .= $this->row($row, $sections, $context);
            }

            return $html;
        }

        // The preview's page data keeps the reading order, so what the
        // sides add is set aside here and put back around Content.
        $sides = self::sides($content);
        $drawnSides = [];
        $sideData = [];
        foreach (Layout::SIDES as $which) {
            $mark = count($this->pageData);
            $drawnSides[$which] = $this->side($sides[$which], $sections, $context);
            $sideData[$which] = array_splice($this->pageData, $mark);
        }

        // A side that shows nothing (its sections hidden, or with nothing to
        // show this shopper) is not drawn and takes no room. The editor's
        // preview keeps every side that holds a section, so the editor can
        // still reach a hidden one.
        foreach (Layout::SIDES as $which) {
            $shows = $context->preview ? $sides[$which] !== [] : self::shows($drawnSides[$which]);
            if (! $shows) {
                $drawnSides[$which] = '';
            }
        }
        $drawnSides = array_filter($drawnSides, fn (string $html) => $html !== '');
        $this->contentSpan = Layout::contentSpan(count($drawnSides), $context->span);

        $html = array_fill_keys(Layout::AREAS, '');
        $inner = $context->withSpan($this->contentSpan);
        foreach (Layout::AREAS as $area) {
            if ($area === 'content') {
                array_push($this->pageData, ...$sideData['left']);
            }
            foreach ($rows as $row) {
                if (self::areaOf($row) === $area) {
                    $html[$area] .= $this->row($row, $sections, $area === 'content' ? $inner : $context);
                }
            }
            if ($area === 'content') {
                array_push($this->pageData, ...$sideData['right']);
            }
        }

        // Joined in area order, which is the stored row order: the page was
        // normalized (Storefront::template()), and normalize keeps rows in area order.
        if ($drawnSides === []) {
            return implode('', $html);
        }

        return $html['top'] . $this->sidesWrapper($drawnSides, $html['content']) . $html['bottom'];
    }

    /**
     * Content between the sides that show something, left before it and
     * right after it, so the reading order and a phone's stacking order
     * agree. A side is a named region, not an aside: it stands inside
     * main, where a complementary landmark does not belong.
     *
     * @param  array<'left'|'right', string>  $sides  the drawn sides
     */
    private function sidesWrapper(array $sides, string $content): string
    {
        $which = count($sides) === 2 ? 'both' : (string) array_key_first($sides);
        $side = fn (string $at, string $label) => isset($sides[$at])
            ? '<section class="xp__side xp__side--' . $at . '" aria-label="' . e(__($label)) . '">' . $sides[$at] . '</section>'
            : '';

        return '<div class="xp" data-sides="' . $which . '">'
            . $side('left', 'Left side')
            . '<div class="xp__content" data-content="' . $this->contentSpan . '">' . $content . '</div>'
            . $side('right', 'Right side')
            . '</div>';
    }

    /** Whether drawn HTML shows anything: more than section markers and white space. */
    private static function shows(string $html): bool
    {
        return trim((string) preg_replace('/<!--\/?xelga:[^>]*-->/', '', $html)) !== '';
    }

    /**
     * One side's sections, each drawn three twelfths wide. render() drops
     * a side that shows nothing.
     *
     * @param  list<string>  $ids
     * @param  array<string, array>  $sections  the document's sections
     */
    private function side(array $ids, array $sections, RenderContext $context): string
    {
        $context = $context->inSide();
        $html = '';
        foreach ($ids as $id) {
            $html .= $this->placed($id, $sections, $context);
        }

        return $html;
    }

    /**
     * The document's rows, worked out once per draw. On a page template
     * with sides each row has its area (see Layout::rows()). A page without
     * sides needs no areas: its rows draw one after another in their
     * stored order whatever their area, so its layout rules are not read.
     * When the rules fail, the failure is reported and the rows are drawn
     * without areas, all in Content, so the shopper still gets the page.
     *
     * @return list<array>
     */
    private function rowsOf(array $content, RenderContext $context): array
    {
        if ($context->group !== 'template' || ! is_array($content['sides'] ?? null) || $content['sides'] === []) {
            return Layout::rows($content);
        }
        try {
            return Layout::rows($content, app(LayoutRules::class, ['handle' => $context->handle]));
        } catch (\Throwable $e) {
            report($e);

            return Layout::rows($content);
        }
    }

    /** A row's area; a row without one (a page without areas) is Content. */
    private static function areaOf(array $row): string
    {
        return in_array($row['area'] ?? null, Layout::AREAS, true) ? $row['area'] : 'content';
    }

    /**
     * The types of the sections the document will draw, each once: a type
     * the catalog knows, a section not hidden, and an extension section
     * only where it may be placed. Hidden sections are left out in the
     * editor preview too, so what a section decides from this list looks
     * the same there as on the store.
     *
     * @return list<string>
     */
    public function drawnTypes(array $content, RenderContext $context): array
    {
        return array_keys($this->drawnIds($this->rowsOf($content, $context), $content, $context));
    }

    /**
     * The same types, each with the id of its first section in reading
     * order, so a section can link to another one on the page.
     *
     * @return array<string, string> type => section id
     */
    private function drawnIds(array $rows, array $content, RenderContext $context): array
    {
        $sections = (array) ($content['sections'] ?? []);
        $placeable = $this->placeableIds($rows, $content, $context);
        $ids = [];
        foreach (self::drawOrder($rows, $content) as $id) {
            $section = $sections[$id] ?? null;
            $schema = is_array($section) ? Catalog::section((string) ($section['type'] ?? '')) : null;
            if ($schema === null || ($schema->source() !== null && ! isset($placeable[$id])) || (bool) ($section['disabled'] ?? false)) {
                continue;
            }
            $ids[$schema->type] ??= $id;
        }

        return $ids;
    }

    /**
     * The extension sections of the document that may be drawn where they
     * sit, counted in reading order (see drawOrder()), hidden
     * ones included, as a save counts them.
     *
     * @return array<string, true>
     */
    private function placeableIds(array $rows, array $content, RenderContext $context): array
    {
        $sections = (array) ($content['sections'] ?? []);
        $counts = [];
        $ids = [];
        foreach (self::drawOrder($rows, $content) as $id) {
            $section = $sections[$id] ?? null;
            $schema = is_array($section) ? Catalog::section((string) ($section['type'] ?? '')) : null;
            if ($schema !== null && $schema->source() !== null && $this->placeable($schema, $context, $counts)) {
                $ids[$id] = true;
            }
        }

        return $ids;
    }

    /**
     * One row. A one-column row draws its section as it always has, with
     * no wrapper; a row of columns draws a grid, each column marked with
     * its width in twelfths and its sections drawn for that width. An
     * empty column keeps its place in the grid; a row with nothing to show
     * a shopper is left out.
     *
     * @param  array{id: string, split: string, columns: list<list<string>>, settings: array}  $row
     */
    private function row(array $row, array $sections, RenderContext $context): string
    {
        $parts = Layout::SPLITS[$row['split']]['parts'] ?? [12];
        if (count($parts) === 1) {
            $html = '';
            foreach (array_merge(...($row['columns'] ?: [[]])) as $id) {
                $html .= $this->placed($id, $sections, $context);
            }

            return $html;
        }

        $columns = '';
        $drawn = false;
        foreach ($parts as $index => $part) {
            $inner = '';
            // A column takes its part of the content area, which may be narrower than the page.
            $span = (int) round($part * $context->span / 12);
            foreach ($row['columns'][$index] ?? [] as $id) {
                $inner .= $this->placed($id, $sections, $context->withSpan($span));
            }
            $drawn = $drawn || $inner !== '';
            $columns .= '<div class="xr__col" data-span="' . $part . '">' . $inner . '</div>';
        }
        if (! $drawn && ! $context->preview) {
            return '';
        }

        $settings = $row['settings'];
        $choice = fn (string $key) => in_array($settings[$key] ?? null, Layout::ROW_CHOICES[$key], true) ? $settings[$key] : Layout::ROW_DEFAULTS[$key];
        $scheme = is_string($settings['scheme'] ?? null) && preg_match(Shape::SCHEME_ID, $settings['scheme']) === 1 ? ' ' . $settings['scheme'] : '';
        $id = (string) $row['id'];
        $html = '<div class="xr' . $scheme . '" data-row-id="' . e($id) . '" data-split="' . e($row['split']) . '" data-width="' . e($choice('width')) . '" data-space="' . e($choice('space')) . '">'
            . '<div class="xr__inner">' . $columns . '</div></div>';

        return preg_match(Shape::ID, $id) === 1 ? $this->wrap($id, $html) : $html;
    }

    /** A section of the document by its id, or nothing when the id names none. */
    private function placed(mixed $id, array $sections, RenderContext $context): string
    {
        $section = is_string($id) ? ($sections[$id] ?? null) : null;

        return is_array($section) ? $this->section($id, $section, $context) : '';
    }

    /**
     * Section ids in the order the page reads them: Top's rows, the left
     * side, Content's rows, the right side, Bottom's rows, each row's
     * columns left to right. A page without areas reads the left side, its
     * rows, the right side.
     *
     * @param  list<array>  $rows  the document's rows (see rowsOf())
     * @return list<string>
     */
    private static function drawOrder(array $rows, array $content): array
    {
        $sides = self::sides($content);
        $ids = [];
        foreach (Layout::AREAS as $area) {
            if ($area === 'content') {
                array_push($ids, ...$sides['left']);
            }
            foreach ($rows as $row) {
                if (self::areaOf($row) === $area) {
                    foreach ($row['columns'] as $column) {
                        array_push($ids, ...$column);
                    }
                }
            }
            if ($area === 'content') {
                array_push($ids, ...$sides['right']);
            }
        }

        return $ids;
    }

    /** @return array{left: list<string>, right: list<string>} */
    private static function sides(array $content): array
    {
        $given = is_array($content['sides'] ?? null) ? $content['sides'] : [];
        $sides = [];
        foreach (Layout::SIDES as $which) {
            $sides[$which] = array_values(array_filter(is_array($given[$which] ?? null) ? $given[$which] : [], 'is_string'));
        }

        return $sides;
    }

    /**
     * How wide the last page template drawn had its content area, in
     * twelfths of the page: 12 until one is drawn, and for a page without
     * sides.
     */
    public function contentSpan(): int
    {
        return $this->contentSpan;
    }

    /** @return list<array> */
    public function pageData(): array
    {
        return $this->pageData;
    }

    private function section(string $id, array $section, RenderContext $context): string
    {
        $type = (string) ($section['type'] ?? '');
        $schema = Catalog::section($type);
        $disabled = (bool) ($section['disabled'] ?? false);

        if ($schema === null) {
            if (! $context->preview) {
                return '';
            }
            // The preview draws a note in its place; the editor knows it is
            // shown, and names it by its own name rather than its id.
            $this->pageData[] = [
                'id' => $id,
                'type' => $type,
                'group' => $context->group,
                'label' => self::nameOf($section),
                'disabled' => (bool) ($section['disabled'] ?? false),
                'blocks' => [],
            ];

            return $this->wrap($id, view('xelga::snippets.section-missing', ['id' => $id, 'type' => $type, 'name' => self::nameOf($section)] + self::owner($type))->render());
        }
        // Where an extension section sits is checked again here, whatever
        // wrote the stored document: one that breaks its schema's rules is
        // not drawn, so its data class never runs on a page it was not
        // made for.
        if ($schema->source() !== null && ! isset($this->placeableIds[$id])) {
            return '';
        }
        if ($disabled && ! $context->preview) {
            return '';
        }
        // Stored while nothing could check it (its extension was away, or a
        // placement put it there): cleaned now, so no view sees an unchecked value.
        if (($section['unchecked'] ?? false) === true) {
            $section = $this->checked($schema, $section);
        }

        $settings = array_merge($schema->defaults(), (array) ($section['settings'] ?? []));
        $blocks = [];
        $liveBlocks = 0;
        $perBlockType = [];
        foreach ((array) ($section['block_order'] ?? []) as $blockId) {
            $block = $section['blocks'][$blockId] ?? null;
            $blockSpec = is_array($block) ? $schema->blockType((string) ($block['type'] ?? '')) : null;
            if (! is_string($blockId) || $blockSpec === null) {
                continue;
            }
            // Counted as a save counts them, hidden ones included. An
            // extension block (or any block of an extension section) past
            // the section's maximum or its type's limit is not drawn.
            $liveBlocks++;
            $perBlockType[$block['type']] = ($perBlockType[$block['type']] ?? 0) + 1;
            if (($schema->source() !== null || is_string($blockSpec['source'] ?? null))
                && ($liveBlocks > $schema->blockMax() || (isset($blockSpec['limit']) && $perBlockType[$block['type']] > (int) $blockSpec['limit']))) {
                continue;
            }
            $blockDisabled = (bool) ($block['disabled'] ?? false);
            if ($blockDisabled && ! $context->preview) {
                continue;
            }
            if (($block['unchecked'] ?? false) === true) {
                $block['settings'] = (new \Themes\Xelga\Settings\Sanitizer($this->sanitizeContext()))
                    ->cleanMap($schema->blockSettings((string) $block['type']), (array) ($block['settings'] ?? []), "blocks.{$blockId}");
            }
            $entry = [
                'id' => $blockId,
                'type' => (string) $block['type'],
                'label' => (string) ($block['label'] ?? ''),
                'disabled' => $blockDisabled,
                // A published page saved before a setting moved down to the blocks still draws as it was.
                'settings' => array_merge($schema->blockDefaults((string) $block['type']), $schema->carryDown((string) $block['type'], (array) ($section['settings'] ?? []), (array) ($block['settings'] ?? []))),
            ];
            $spec = $schema->blockType((string) $block['type']);
            if (is_string($spec['source'] ?? null)) {
                $entry['source'] = $spec['source'];
                $entry['html'] = $this->extensionBlock($spec, $entry, $id, $context);
            }
            $blocks[] = $entry;
        }

        $label = (string) (($section['label'] ?? '') !== '' ? $section['label'] : $schema->label);
        $this->pageData[] = [
            'id' => $id,
            'type' => $type,
            'group' => $context->group,
            'label' => $label,
            'disabled' => $disabled,
            'blocks' => array_map(fn (array $b) => ['id' => $b['id'], 'type' => $b['type'], 'label' => $b['label'] !== '' ? $b['label'] : (string) ($schema->blockType($b['type'])['label'] ?? $b['type']), 'disabled' => $b['disabled']], $blocks),
        ];

        try {
            $data = [];
            $provider = $schema->dataClass();
            if ($provider !== null && class_exists($provider) && is_subclass_of($provider, SectionData::class)) {
                $data = app($provider)->resolve($settings, $blocks, $context);
            }

            $html = ($schema->source() === null ? view($schema->view()) : view()->file((string) $schema->viewFile()))->with([
                'section' => ['id' => $id, 'type' => $type, 'label' => $label, 'disabled' => $disabled, 'class' => $schema->cssClass()],
                'settings' => $settings,
                'blocks' => $blocks,
                'data' => $data,
                'context' => $context,
            ])->render();
        } catch (\Throwable $e) {
            report($e);
            $html = $context->preview
                ? view('xelga::snippets.section-error', ['id' => $id, 'label' => $label])->render()
                : '';
        }

        return $this->wrap($id, $html);
    }

    /**
     * Whether an extension section may be drawn here: in a page template it
     * is allowed in, as a main section only on its own page type, and no
     * more of it than its limit allows, counted in $counts.
     *
     * @param  array<string, int>  $counts  extension sections met so far in the document, by type
     */
    private function placeable(\Themes\Xelga\Catalog\SectionSchema $schema, RenderContext $context, array &$counts): bool
    {
        $handle = $context->handle;
        $pageType = Handles::pageType($handle);
        if ($context->group !== 'template' || Handles::kind($handle) !== 'template' || ! $schema->allowedIn($handle, 'template', $pageType)) {
            return false;
        }

        $main = ($schema->raw['main'] ?? false) === true;
        if ($main && ! in_array($schema->type, Defaults::mainTypes((string) $pageType), true)) {
            return false;
        }

        $count = $counts[$schema->type] = ($counts[$schema->type] ?? 0) + 1;
        $limit = $main ? 1 : $schema->limit();

        return $limit === null || $count <= $limit;
    }

    /**
     * A section the catalog lacks, by its own name: the label it was saved
     * with, else its type made readable (`marketplace.seller-card` reads
     * "Seller card").
     */
    public static function nameOf(array $section): string
    {
        $label = trim((string) ($section['label'] ?? ''));
        if ($label !== '') {
            return $label;
        }
        $type = (string) ($section['type'] ?? '');
        $name = str_contains($type, '.') ? substr($type, (int) strpos($type, '.') + 1) : $type;

        return \Illuminate\Support\Str::ucfirst(str_replace('-', ' ', $name)) ?: 'Section';
    }

    /**
     * Who a missing extension section belongs to, for the editor's note.
     *
     * @return array{extension: ?string, absence: ?string}
     */
    public static function owner(string $type): array
    {
        $name = \Themes\Xelga\Documents\Shape::extensionOf($type);
        if ($name === null) {
            return ['extension' => null, 'absence' => null];
        }
        $extensions = \Themes\Xelga\Extensions\Extensions::current();

        return ['extension' => $extensions->titleOf($name), 'absence' => $extensions->absence($name)];
    }

    /**
     * An extension block drawn by its own view, inside the theme's block
     * wrapper (which carries the ids the editor selects it by). A block that
     * fails is logged and left out; the section still draws.
     *
     * @param  array{id: string, type: string, label: string, disabled: bool, settings: array}  $block
     */
    private function extensionBlock(array $spec, array $block, string $sectionId, RenderContext $context): HtmlString
    {
        $part = \Themes\Xelga\Extensions\Extensions::current()->get((string) $spec['source']);
        $file = $part === null ? null : $part->path . '/views/blocks/' . $block['type'] . '.blade.php';
        if ($file === null || ! is_file($file) || is_link($file)) {
            return new HtmlString('');
        }

        try {
            $data = [];
            $class = $spec['data_class'] ?? null;
            if (is_string($class) && class_exists($class) && is_subclass_of($class, SectionData::class)) {
                $data = app($class)->resolve($block['settings'], [], $context);
            }
            $inner = view()->file($file, [
                'block' => $block,
                'settings' => $block['settings'],
                'data' => $data,
                'context' => $context,
                'section' => ['id' => $sectionId],
            ])->render();
        } catch (\Throwable $e) {
            report($e);
            if (! $context->preview) {
                return new HtmlString('');
            }
            $inner = '';
        }

        return new HtmlString(view('xelga::snippets.extension-block', ['block' => $block, 'class' => (string) $spec['class'], 'inner' => new HtmlString($inner)])->render());
    }

    /** A section's settings and blocks run through the sanitizer, as a save would. */
    private function checked(\Themes\Xelga\Catalog\SectionSchema $schema, array $section): array
    {
        $sanitizer = new \Themes\Xelga\Settings\Sanitizer($this->sanitizeContext());
        $stored = (array) ($section['settings'] ?? []);
        $section['settings'] = $sanitizer->cleanMap($schema->settings(), $stored, 'settings');
        foreach ((array) ($section['blocks'] ?? []) as $blockId => $block) {
            $type = is_array($block) ? (string) ($block['type'] ?? '') : '';
            if ($schema->blockType($type) === null) {
                unset($section['blocks'][$blockId]);
                continue;
            }
            $section['blocks'][$blockId]['settings'] = $sanitizer->cleanMap(
                $schema->blockSettings($type),
                $schema->carryDown($type, $stored, (array) ($block['settings'] ?? [])),
                "blocks.{$blockId}",
            );
        }

        return $section;
    }

    private ?\Themes\Xelga\Settings\SanitizeContext $sanitizeContext = null;

    private function sanitizeContext(): \Themes\Xelga\Settings\SanitizeContext
    {
        if ($this->sanitizeContext === null) {
            $settings = app(DocumentRepository::class)->content(\Themes\Xelga\Documents\Handles::SETTINGS);
            $ids = array_values(array_filter(array_column((array) ($settings['schemes'] ?? []), 'id'), 'is_string'));
            $this->sanitizeContext = \Themes\Xelga\Settings\SanitizeContext::forStore($ids !== [] ? $ids : ['scheme-1']);
        }

        return $this->sanitizeContext;
    }

    private function wrap(string $id, string $html): string
    {
        return "<!--xelga:{$id}-->" . $html . "<!--/xelga:{$id}-->";
    }
}
