<?php

namespace Themes\Xelga\Rendering;

use Illuminate\Support\HtmlString;
use Themes\Xelga\Catalog\Catalog;
use Themes\Xelga\Documents\Defaults;
use Themes\Xelga\Documents\Handles;

/**
 * Draws a document's sections in order. Settings are merged over the
 * schema's defaults, so a setting added in a theme update needs no data
 * change. Each section is wrapped in comment markers, and one that fails
 * is logged and left out, so a bad section never takes the page down.
 */
final class SectionRenderer
{
    /** @var list<array> what the editor preview needs to know about the drawn sections */
    private array $pageData = [];

    /** @var array<string, int> extension sections met so far in the document being drawn, by type */
    private array $extensionCounts = [];

    public function render(array $content, RenderContext $context): string
    {
        $html = '';
        $sections = (array) ($content['sections'] ?? []);
        $this->extensionCounts = [];

        foreach ((array) ($content['order'] ?? []) as $id) {
            $section = $sections[$id] ?? null;
            if (! is_string($id) || ! is_array($section)) {
                continue;
            }
            $html .= $this->section($id, $section, $context);
        }

        return $html;
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
        if ($schema->source() !== null && ! $this->placeable($schema, $context)) {
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
     * more of it than its limit allows.
     */
    private function placeable(\Themes\Xelga\Catalog\SectionSchema $schema, RenderContext $context): bool
    {
        $handle = $context->handle;
        $pageType = Handles::pageType($handle);
        if ($context->group !== 'template' || Handles::kind($handle) !== 'template' || ! $schema->allowedIn($handle, 'template', $pageType)) {
            return false;
        }

        $main = ($schema->raw['main'] ?? false) === true;
        if ($main && ! in_array($schema->type, self::mainTypes((string) $pageType), true)) {
            return false;
        }

        $count = $this->extensionCounts[$schema->type] = ($this->extensionCounts[$schema->type] ?? 0) + 1;
        $limit = $main ? 1 : $schema->limit();

        return $limit === null || $count <= $limit;
    }

    /**
     * The main section types of a page type, from its shipped template.
     *
     * @return list<string>
     */
    private static function mainTypes(string $pageType): array
    {
        $types = [];
        foreach ((array) (Defaults::content($pageType)['sections'] ?? []) as $section) {
            $schema = Catalog::section((string) (is_array($section) ? ($section['type'] ?? '') : ''));
            if ($schema !== null && ($schema->raw['main'] ?? false) === true) {
                $types[] = $schema->type;
            }
        }

        return $types;
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
